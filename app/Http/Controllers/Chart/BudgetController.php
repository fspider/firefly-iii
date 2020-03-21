<?php
/**
 * BudgetController.php
 * Copyright (c) 2019 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace FireflyIII\Http\Controllers\Chart;

use Carbon\Carbon;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Generator\Chart\Basic\GeneratorInterface;
use FireflyIII\Helpers\Collector\GroupCollectorInterface;
use FireflyIII\Http\Controllers\Controller;
use FireflyIII\Models\TransactionCurrency;
use FireflyIII\Models\Budget;
use FireflyIII\Models\BudgetLimit;
use FireflyIII\Models\TransactionType;
use FireflyIII\Repositories\Budget\BudgetLimitRepositoryInterface;
use FireflyIII\Repositories\Budget\BudgetRepositoryInterface;
use FireflyIII\Repositories\Budget\NoBudgetRepositoryInterface;
use FireflyIII\Repositories\Budget\OperationsRepositoryInterface;
use FireflyIII\Support\CacheProperties;
use FireflyIII\Support\Http\Controllers\AugumentData;
use FireflyIII\Support\Http\Controllers\DateCalculation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Class BudgetController.
 *
 */
class BudgetController extends Controller
{
    use DateCalculation, AugumentData;
    /** @var GeneratorInterface Chart generation methods. */
    protected $generator;
    /** @var OperationsRepositoryInterface */
    protected $opsRepository;
    /** @var BudgetRepositoryInterface The budget repository */
    protected $repository;
    /** @var BudgetLimitRepositoryInterface */
    private $blRepository;
    /** @var NoBudgetRepositoryInterface */
    private $nbRepository;

    /**
     * BudgetController constructor.
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        parent::__construct();

        $this->middleware(
            function ($request, $next) {
                $this->generator     = app(GeneratorInterface::class);
                $this->repository    = app(BudgetRepositoryInterface::class);
                $this->opsRepository = app(OperationsRepositoryInterface::class);
                $this->blRepository  = app(BudgetLimitRepositoryInterface::class);
                $this->nbRepository  = app(NoBudgetRepositoryInterface::class);

                return $next($request);
            }
        );
    }


    /**
     * Shows overview of a single budget.
     *
     * @param Budget $budget
     *
     * @return JsonResponse
     */
    public function budget(Budget $budget): JsonResponse
    {
        /** @var Carbon $start */
        $start = $this->repository->firstUseDate($budget) ?? session('start', new Carbon);
        /** @var Carbon $end */
        $end   = session('end', new Carbon);
        $cache = new CacheProperties();
        $cache->addProperty($start);
        $cache->addProperty($end);
        $cache->addProperty('chart.budget.budget');
        $cache->addProperty($budget->id);

        if ($cache->has()) {
            return response()->json($cache->get()); // @codeCoverageIgnore
        }
        $step       = $this->calculateStep($start, $end); // depending on diff, do something with range of chart.
        $collection = new Collection([$budget]);
        $chartData  = [];
        $loopStart  = clone $start;
        $loopStart  = app('navigation')->startOfPeriod($loopStart, $step);
        $currencies     = [];
        $defaultEntries = [];
        //        echo '<hr>';
        while ($end >= $loopStart) {
            /** @var Carbon $currentEnd */
            $loopEnd = app('navigation')->endOfPeriod($loopStart, $step);
            if ('1Y' === $step) {
                $loopEnd->subDay(); // @codeCoverageIgnore
            }
            $spent = $this->opsRepository->sumExpenses($loopStart, $loopEnd, null, $collection);
            $label = trim(app('navigation')->periodShow($loopStart, $step));

            foreach ($spent as $row) {
                $currencyId              = $row['currency_id'];
                $currencies[$currencyId] = $currencies[$currencyId] ?? $row; // don't mind the field 'sum'
                // also store this day's sum:
                $currencies[$currencyId]['spent'][$label] = $row['sum'];
            }
            $defaultEntries[$label] = 0;
            // set loop start to the next period:
            $loopStart = clone $loopEnd;
            $loopStart->addSecond();
        }
        // loop all currencies:
        foreach ($currencies as $currencyId => $currency) {
            $chartData[$currencyId] = [
                'label'         => count($currencies) > 1 ? sprintf('%s (%s)', $budget->name, $currency['currency_name']) : $budget->name,
                'type'          => 'bar',
                'currency_symbol' => $currency['currency_symbol'],
                'entries'       => $defaultEntries,
            ];
            foreach ($currency['spent'] as $label => $spent) {
                $chartData[$currencyId]['entries'][$label] = round(bcmul($spent, '-1'), $currency['currency_decimal_places']);
            }
        }
        $data = $this->generator->multiSet(array_values($chartData));
        $cache->store($data);

        return response()->json($data);
    }


    /**
     * Shows the amount left in a specific budget limit.
     *
     * @param Budget      $budget
     * @param BudgetLimit $budgetLimit
     *
     * @return JsonResponse
     *
     * @throws FireflyException
     */
    public function budgetLimit(Budget $budget, BudgetLimit $budgetLimit): JsonResponse
    {
        if ($budgetLimit->budget->id !== $budget->id) {
            throw new FireflyException('This budget limit is not part of this budget.');
        }

        $start = clone $budgetLimit->start_date;
        $end   = clone $budgetLimit->end_date;
        $cache = new CacheProperties();
        $cache->addProperty($start);
        $cache->addProperty($end);
        $cache->addProperty('chart.budget.budget.limit');
        $cache->addProperty($budgetLimit->id);
        $cache->addProperty($budget->id);

        if ($cache->has()) {
            return response()->json($cache->get()); // @codeCoverageIgnore
        }

        $entries          = [];
        $amount           = $budgetLimit->amount;
        $budgetCollection = new Collection([$budget]);
        while ($start <= $end) {
            $spent            = $this->opsRepository->spentInPeriod($budgetCollection, new Collection, $start, $start);
            $amount           = bcadd($amount, $spent);
            $format           = $start->formatLocalized((string)trans('config.month_and_day'));
            $entries[$format] = $amount;

            $start->addDay();
        }
        $data = $this->generator->singleSet((string)trans('firefly.left'), $entries);
        // add currency symbol from budget limit:
        $data['datasets'][0]['currency_symbol'] = $budgetLimit->transactionCurrency->symbol;
        $cache->store($data);

        return response()->json($data);
    }


    /**
     * Shows how much is spent per asset account.
     *
     * @param Budget           $budget
     * @param BudgetLimit|null $budgetLimit
     *
     * @return JsonResponse
     */
    public function expenseAsset(Budget $budget, ?BudgetLimit $budgetLimit = null): JsonResponse
    {
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $budgetLimitId = null === $budgetLimit ? 0 : $budgetLimit->id;
        $cache         = new CacheProperties;
        $cache->addProperty($budget->id);
        $cache->addProperty($budgetLimitId);
        $cache->addProperty('chart.budget.expense-asset');
        $collector->setRange(session()->get('start'), session()->get('end'));
        $start = session()->get('start');
        $end   = session()->get('end');
        if (null !== $budgetLimit) {
            $start = $budgetLimit->start_date;
            $end   = $budgetLimit->end_date;
            $collector->setRange($budgetLimit->start_date, $budgetLimit->end_date)->setCurrency($budgetLimit->transactionCurrency);
        }
        $cache->addProperty($start);
        $cache->addProperty($end);

        if ($cache->has()) {
            return response()->json($cache->get()); // @codeCoverageIgnore
        }
        $collector->setBudget($budget);
        $journals  = $collector->getExtractedJournals();
        $result    = [];
        $chartData = [];

        // group by asset account ID:
        foreach ($journals as $journal) {
            $key                    = sprintf('%d-%d', (int)$journal['source_account_id'], $journal['currency_id']);
            $result[$key]           = $result[$key] ?? [
                    'amount'          => '0',
                    'currency_symbol' => $journal['currency_symbol'],
                    'currency_name'   => $journal['currency_name'],
                ];
            $result[$key]['amount'] = bcadd($journal['amount'], $result[$key]['amount']);
        }

        $names = $this->getAccountNames(array_keys($result));
        foreach ($result as $combinedId => $info) {
            $parts   = explode('-', $combinedId);
            $assetId = (int)$parts[0];
            $title   = sprintf('%s (%s)', $names[$assetId] ?? '(empty)', $info['currency_name']);
            $chartData[$title]
                     = [
                'amount'          => $info['amount'],
                'currency_symbol' => $info['currency_symbol'],
            ];
        }

        $data = $this->generator->multiCurrencyPieChart($chartData);
        $cache->store($data);

        return response()->json($data);
    }


    /**
     * Shows how much is spent per category.
     *
     * @param Budget           $budget
     * @param BudgetLimit|null $budgetLimit
     *
     * @return JsonResponse
     */
    public function expenseCategory(Budget $budget, ?BudgetLimit $budgetLimit = null): JsonResponse
    {
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $budgetLimitId = null === $budgetLimit ? 0 : $budgetLimit->id;
        $cache         = new CacheProperties;
        $cache->addProperty($budget->id);
        $cache->addProperty($budgetLimitId);
        $cache->addProperty('chart.budget.expense-category');
        $collector->setRange(session()->get('start'), session()->get('end'));
        $start = session()->get('start');
        $end   = session()->get('end');
        if (null !== $budgetLimit) {
            $start = $budgetLimit->start_date;
            $end   = $budgetLimit->end_date;
            $collector->setRange($budgetLimit->start_date, $budgetLimit->end_date)->setCurrency($budgetLimit->transactionCurrency);
        }
        $cache->addProperty($start);
        $cache->addProperty($end);

        if ($cache->has()) {
            return response()->json($cache->get()); // @codeCoverageIgnore
        }
        $collector->setBudget($budget)->withCategoryInformation();
        $journals  = $collector->getExtractedJournals();
        $result    = [];
        $chartData = [];
        foreach ($journals as $journal) {
            $key                    = sprintf('%d-%d', $journal['category_id'], $journal['currency_id']);
            $result[$key]           = $result[$key] ?? [
                    'amount'          => '0',
                    'currency_symbol' => $journal['currency_symbol'],
                    'currency_name'   => $journal['currency_name'],
                ];
            $result[$key]['amount'] = bcadd($journal['amount'], $result[$key]['amount']);
        }

        $names = $this->getCategoryNames(array_keys($result));
        foreach ($result as $combinedId => $info) {
            $parts             = explode('-', $combinedId);
            $categoryId        = (int)$parts[0];
            $title             = sprintf('%s (%s)', $names[$categoryId] ?? '(empty)', $info['currency_name']);
            $chartData[$title] = [
                'amount'          => $info['amount'],
                'currency_symbol' => $info['currency_symbol'],
            ];
        }
        $data = $this->generator->multiCurrencyPieChart($chartData);
        $cache->store($data);

        return response()->json($data);
    }


    /**
     * Shows how much is spent per expense account.
     *
     *
     * @param Budget           $budget
     * @param BudgetLimit|null $budgetLimit
     *
     * @return JsonResponse
     */
    public function expenseExpense(Budget $budget, ?BudgetLimit $budgetLimit = null): JsonResponse
    {
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $budgetLimitId = null === $budgetLimit ? 0 : $budgetLimit->id;
        $cache         = new CacheProperties;
        $cache->addProperty($budget->id);
        $cache->addProperty($budgetLimitId);
        $cache->addProperty('chart.budget.expense-expense');
        $collector->setRange(session()->get('start'), session()->get('end'));
        $start = session()->get('start');
        $end   = session()->get('end');
        if (null !== $budgetLimit) {
            $start = $budgetLimit->start_date;
            $end   = $budgetLimit->end_date;
            $collector->setRange($budgetLimit->start_date, $budgetLimit->end_date)->setCurrency($budgetLimit->transactionCurrency);
        }
        $cache->addProperty($start);
        $cache->addProperty($end);

        if ($cache->has()) {
            return response()->json($cache->get()); // @codeCoverageIgnore
        }

        $collector->setTypes([TransactionType::WITHDRAWAL])->setBudget($budget)->withAccountInformation();
        $journals  = $collector->getExtractedJournals();
        $result    = [];
        $chartData = [];
        /** @var array $journal */
        foreach ($journals as $journal) {
            $key                    = sprintf('%d-%d', $journal['destination_account_id'], $journal['currency_id']);
            $result[$key]           = $result[$key] ?? [
                    'amount'          => '0',
                    'currency_symbol' => $journal['currency_symbol'],
                    'currency_name'   => $journal['currency_name'],
                ];
            $result[$key]['amount'] = bcadd($journal['amount'], $result[$key]['amount']);
        }

        $names = $this->getAccountNames(array_keys($result));
        foreach ($result as $combinedId => $info) {
            $parts             = explode('-', $combinedId);
            $opposingId        = (int)$parts[0];
            $name              = $names[$opposingId] ?? 'no name';
            $title             = sprintf('%s (%s)', $name, $info['currency_name']);
            $chartData[$title] = [
                'amount'          => $info['amount'],
                'currency_symbol' => $info['currency_symbol'],
            ];
        }

        $data = $this->generator->multiCurrencyPieChart($chartData);
        $cache->store($data);

        return response()->json($data);
    }


    /**
     * Shows a budget list with spent/left/overspent.
     *
     * TODO there are cases when this chart hides expenses: when budget has limits
     * and limits are found and used, but the expense is in another currency.
     *
     * @return JsonResponse
     *
     */
    public function frontpage(): JsonResponse
    {
        $start = session('start', Carbon::now()->startOfMonth());
        $end   = session('end', Carbon::now()->endOfMonth());
        // chart properties for cache:
        $cache = new CacheProperties();
        $cache->addProperty($start);
        $cache->addProperty($end);
        $cache->addProperty('chart.budget.frontpage');
        if ($cache->has()) {
            return response()->json($cache->get()); // @codeCoverageIgnore
        }
        $budgets   = $this->repository->getActiveBudgets();
        $chartData = [
            ['label' => (string)trans('firefly.spent_in_budget'), 'entries' => [], 'type' => 'bar'],
            ['label' => (string)trans('firefly.left_to_spend'), 'entries' => [], 'type' => 'bar'],
            ['label' => (string)trans('firefly.overspent'), 'entries' => [], 'type' => 'bar'],
        ];

        /** @var Budget $budget */
        foreach ($budgets as $budget) {
            $limits = $this->blRepository->getBudgetLimits($budget, $start, $end);
            if (0 === $limits->count()) {
                $spent = $this->opsRepository->sumExpenses($start, $end, null, new Collection([$budget]), null);
                /** @var array $entry */
                foreach ($spent as $entry) {
                    $title                           = sprintf('%s (%s)', $budget->name, $entry['currency_name']);
                    $chartData[0]['entries'][$title] = bcmul($entry['sum'], '-1'); // spent
                    $chartData[1]['entries'][$title] = 0; // left to spend
                    $chartData[2]['entries'][$title] = 0; // overspent
                }
            }
            if (0 !== $limits->count()) {
                /** @var BudgetLimit $limit */
                foreach ($limits as $limit) {
                    $spent = $this->opsRepository->sumExpenses(
                        $limit->start_date, $limit->end_date, null, new Collection([$budget]), $limit->transactionCurrency
                    );
                    /** @var array $entry */
                    foreach ($spent as $entry) {
                        $title = sprintf('%s (%s)', $budget->name, $entry['currency_name']);
                        if ($limit->start_date->startOfDay()->ne($start->startOfDay()) || $limit->end_date->startOfDay()->ne($end->startOfDay())) {
                            $title = sprintf(
                                '%s (%s) (%s - %s)', $budget->name, $entry['currency_name'],
                                $limit->start_date->formatLocalized($this->monthAndDayFormat),
                                $limit->end_date->formatLocalized($this->monthAndDayFormat)
                            );
                        }

                        $chartData[0]['entries'][$title] = bcmul($entry['sum'], '-1'); // spent
                        $chartData[1]['entries'][$title] = 1 === bccomp($limit->amount, bcmul($entry['sum'], '-1')) ? bcadd($entry['sum'], $limit->amount)
                            : '0';
                        $chartData[2]['entries'][$title] = 1 === bccomp($limit->amount, bcmul($entry['sum'], '-1')) ?
                            '0' : bcmul(bcadd($entry['sum'], $limit->amount), '-1');
                    }
                }
            }
        }

        $data = $this->generator->multiSet($chartData);
        $cache->store($data);

        return response()->json($data);
    }


    /**
     * Shows a budget overview chart (spent and budgeted).
     *
     * @param Budget              $budget
     * @param TransactionCurrency $currency
     * @param Collection          $accounts
     * @param Carbon              $start
     * @param Carbon              $end
     *
     * @return JsonResponse
     */
    public function period(Budget $budget, TransactionCurrency $currency, Collection $accounts, Carbon $start, Carbon $end): JsonResponse
    {
        // chart properties for cache:
        $cache = new CacheProperties();
        $cache->addProperty($start);
        $cache->addProperty($end);
        $cache->addProperty($accounts);
        $cache->addProperty($budget->id);
        $cache->addProperty($currency->id);
        $cache->addProperty('chart.budget.period');
        if ($cache->has()) {
            // return response()->json($cache->get()); // @codeCoverageIgnore
        }
        $titleFormat    = app('navigation')->preferredCarbonLocalizedFormat($start, $end);
        $preferredRange = app('navigation')->preferredRangeFormat($start, $end);
        $chartData      = [
            [
                'label'           => (string)trans('firefly.box_spent_in_currency', ['currency' => $currency->name]),
                'type'            => 'bar',
                'entries'         => [],
                'currency_symbol' => $currency->symbol,
            ],
            [
                'label'           => (string)trans('firefly.box_budgeted_in_currency', ['currency' => $currency->name]),
                'type'            => 'bar',
                'currency_symbol' => $currency->symbol,
                'entries'         => [],
            ],
        ];

        $currentStart = clone $start;
        while ($currentStart <= $end) {
            $currentStart= app('navigation')->startOfPeriod($currentStart, $preferredRange);
            $title      = $currentStart->formatLocalized($titleFormat);
            $currentEnd = app('navigation')->endOfPeriod($currentStart, $preferredRange);

            // default limit is no limit:
            $chartData[0]['entries'][$title] = 0;

            // default spent is not spent at all.
            $chartData[1]['entries'][$title] = 0;

            // get budget limit in this period for this currency.
            $limit = $this->blRepository->find($budget, $currency, $currentStart, $currentEnd);
            if (null !== $limit) {
                $chartData[1]['entries'][$title] = round($limit->amount, $currency->decimal_places);
            }

            // get spent amount in this period for this currency.
            $sum                             = $this->opsRepository->sumExpenses($currentStart, $currentEnd, $accounts, new Collection([$budget]), $currency);
            $amount                          = app('steam')->positive($sum[$currency->id]['sum'] ?? '0');
            $chartData[0]['entries'][$title] = round($amount, $currency->decimal_places);

            $currentStart = clone $currentEnd;
            $currentStart->addDay()->startOfDay();
        }

        $data = $this->generator->multiSet($chartData);
        $cache->store($data);

        return response()->json($data);
    }


    /**
     * Shows a chart for transactions without a budget.
     *
     * @param TransactionCurrency $currency
     * @param Collection          $accounts
     * @param Carbon              $start
     * @param Carbon              $end
     *
     * @return JsonResponse
     */
    public function periodNoBudget(TransactionCurrency $currency, Collection $accounts, Carbon $start, Carbon $end): JsonResponse
    {
        // chart properties for cache:
        $cache = new CacheProperties();
        $cache->addProperty($start);
        $cache->addProperty($end);
        $cache->addProperty($accounts);
        $cache->addProperty($currency->id);
        $cache->addProperty('chart.budget.no-budget');
        if ($cache->has()) {
            return response()->json($cache->get()); // @codeCoverageIgnore
        }

        // the expenses:
        $titleFormat    = app('navigation')->preferredCarbonLocalizedFormat($start, $end);
        $chartData      = [];
        $currentStart   = clone $start;
        $preferredRange = app('navigation')->preferredRangeFormat($start, $end);
        while ($currentStart <= $end) {
            $currentEnd        = app('navigation')->endOfPeriod($currentStart, $preferredRange);
            $title             = $currentStart->formatLocalized($titleFormat);
            $sum               = $this->nbRepository->sumExpenses($currentStart, $currentEnd, $accounts, $currency);
            $amount            = app('steam')->positive($sum[$currency->id]['sum'] ?? '0');
            $chartData[$title] = round($amount, $currency->decimal_places);
            $currentStart      = app('navigation')->addPeriod($currentStart, $preferredRange, 0);
        }

        $data = $this->generator->singleSet((string)trans('firefly.spent'), $chartData);
        $cache->store($data);

        return response()->json($data);
    }


}
