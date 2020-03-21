<?php
/**
 * CurrencyController.php
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


namespace FireflyIII\Api\V1\Controllers;

use FireflyIII\Api\V1\Requests\CurrencyRequest;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Helpers\Collector\GroupCollectorInterface;
use FireflyIII\Models\Account;
use FireflyIII\Models\Bill;
use FireflyIII\Models\Recurrence;
use FireflyIII\Models\RecurrenceTransaction;
use FireflyIII\Models\Rule;
use FireflyIII\Models\RuleTrigger;
use FireflyIII\Models\TransactionCurrency;
use FireflyIII\Repositories\Account\AccountRepositoryInterface;
use FireflyIII\Repositories\Bill\BillRepositoryInterface;
use FireflyIII\Repositories\Budget\AvailableBudgetRepositoryInterface;
use FireflyIII\Repositories\Budget\BudgetLimitRepositoryInterface;
use FireflyIII\Repositories\Budget\BudgetRepositoryInterface;
use FireflyIII\Repositories\Currency\CurrencyRepositoryInterface;
use FireflyIII\Repositories\Recurring\RecurringRepositoryInterface;
use FireflyIII\Repositories\Rule\RuleRepositoryInterface;
use FireflyIII\Repositories\User\UserRepositoryInterface;
use FireflyIII\Support\Http\Api\AccountFilter;
use FireflyIII\Support\Http\Api\TransactionFilter;
use FireflyIII\Transformers\AccountTransformer;
use FireflyIII\Transformers\AvailableBudgetTransformer;
use FireflyIII\Transformers\BillTransformer;
use FireflyIII\Transformers\BudgetLimitTransformer;
use FireflyIII\Transformers\CurrencyExchangeRateTransformer;
use FireflyIII\Transformers\CurrencyTransformer;
use FireflyIII\Transformers\RecurrenceTransformer;
use FireflyIII\Transformers\RuleTransformer;
use FireflyIII\Transformers\TransactionGroupTransformer;
use FireflyIII\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Resource\Collection as FractalCollection;
use League\Fractal\Resource\Item;

/**
 * Class CurrencyController.
 *
 */
class CurrencyController extends Controller
{
    use AccountFilter, TransactionFilter;
    /** @var CurrencyRepositoryInterface The currency repository */
    private $repository;
    /** @var UserRepositoryInterface The user repository */
    private $userRepository;

    /**
     * CurrencyRepository constructor.
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware(
            function ($request, $next) {
                /** @var User $admin */
                $admin = auth()->user();

                /** @var CurrencyRepositoryInterface repository */
                $this->repository     = app(CurrencyRepositoryInterface::class);
                $this->userRepository = app(UserRepositoryInterface::class);
                $this->repository->setUser($admin);

                return $next($request);
            }
        );
    }

    /**
     * Display a list of accounts.
     *
     * @param Request             $request
     * @param TransactionCurrency $currency
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function accounts(Request $request, TransactionCurrency $currency): JsonResponse
    {
        $manager = $this->getManager();

        // read type from URI
        $type = $request->get('type') ?? 'all';
        $this->parameters->set('type', $type);

        // types to get, page size:
        $types    = $this->mapAccountTypes($this->parameters->get('type'));
        $pageSize = (int)app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;

        // get list of accounts. Count it and split it.
        /** @var AccountRepositoryInterface $accountRepository */
        $accountRepository = app(AccountRepositoryInterface::class);
        $unfiltered        = $accountRepository->getAccountsByType($types);

        // filter list on currency preference:
        $collection = $unfiltered->filter(
            static function (Account $account) use ($currency, $accountRepository) {
                $currencyId = (int)$accountRepository->getMetaValue($account, 'currency_id');

                return $currencyId === $currency->id;
            }
        );

        $count    = $collection->count();
        $accounts = $collection->slice(($this->parameters->get('page') - 1) * $pageSize, $pageSize);

        // make paginator:
        $paginator = new LengthAwarePaginator($accounts, $count, $pageSize, $this->parameters->get('page'));
        $paginator->setPath(route('api.v1.currencies.accounts', [$currency->code]) . $this->buildParams());

        /** @var AccountTransformer $transformer */
        $transformer = app(AccountTransformer::class);
        $transformer->setParameters($this->parameters);


        $resource = new FractalCollection($accounts, $transformer, 'accounts');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * Display a listing of the resource.
     *
     * @param TransactionCurrency $currency
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function availableBudgets(TransactionCurrency $currency): JsonResponse
    {
        /** @var User $admin */
        $admin = auth()->user();

        $manager = $this->getManager();
        // types to get, page size:
        $pageSize = (int)app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;

        // get list of available budgets. Count it and split it.

        /** @var BudgetRepositoryInterface $repository */
        $repository = app(BudgetRepositoryInterface::class);

        /** @var AvailableBudgetRepositoryInterface $abRepository */
        $abRepository = app(AvailableBudgetRepositoryInterface::class);

        $repository->setUser($admin);
        $collection       = $abRepository->getAvailableBudgetsByCurrency($currency);
        $count            = $collection->count();
        $availableBudgets = $collection->slice(($this->parameters->get('page') - 1) * $pageSize, $pageSize);


        // make paginator:
        $paginator = new LengthAwarePaginator($availableBudgets, $count, $pageSize, $this->parameters->get('page'));
        $paginator->setPath(route('api.v1.currencies.available_budgets', [$currency->code]) . $this->buildParams());

        /** @var AvailableBudgetTransformer $transformer */
        $transformer = app(AvailableBudgetTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($availableBudgets, $transformer, 'available_budgets');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * List all bills
     *
     * @param TransactionCurrency $currency
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function bills(TransactionCurrency $currency): JsonResponse
    {
        $manager = $this->getManager();

        /** @var BillRepositoryInterface $repository */
        $repository = app(BillRepositoryInterface::class);
        $pageSize   = (int)app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;
        $unfiltered = $repository->getBills();

        // filter and paginate list:
        $collection = $unfiltered->filter(
            static function (Bill $bill) use ($currency) {
                return $bill->transaction_currency_id === $currency->id;
            }
        );
        $count      = $collection->count();
        $bills      = $collection->slice(($this->parameters->get('page') - 1) * $pageSize, $pageSize);

        // make paginator:
        $paginator = new LengthAwarePaginator($bills, $count, $pageSize, $this->parameters->get('page'));
        $paginator->setPath(route('api.v1.currencies.bills', [$currency->code]) . $this->buildParams());

        /** @var BillTransformer $transformer */
        $transformer = app(BillTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($bills, $transformer, 'bills');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * List all budget limits
     *
     * @param TransactionCurrency $currency
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function budgetLimits(TransactionCurrency $currency): JsonResponse
    {
        /** @var BudgetLimitRepositoryInterface $blRepository */
        $blRepository = app(BudgetLimitRepositoryInterface::class);

        $manager      = $this->getManager();
        $pageSize     = (int)app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;
        $collection   = $blRepository->getAllBudgetLimitsByCurrency($currency, $this->parameters->get('start'), $this->parameters->get('end'));
        $count        = $collection->count();
        $budgetLimits = $collection->slice(($this->parameters->get('page') - 1) * $pageSize, $pageSize);
        $paginator    = new LengthAwarePaginator($budgetLimits, $count, $pageSize, $this->parameters->get('page'));
        $paginator->setPath(route('api.v1.currencies.budget_limits', [$currency->code]) . $this->buildParams());

        /** @var BudgetLimitTransformer $transformer */
        $transformer = app(BudgetLimitTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($budgetLimits, $transformer, 'budget_limits');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * Show a list of known exchange rates
     *
     * @param TransactionCurrency $currency
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function cer(TransactionCurrency $currency): JsonResponse
    {
        // create some objects:
        $manager    = $this->getManager();
        $pageSize   = (int)app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;
        $collection = $this->repository->getExchangeRates($currency);


        $count         = $collection->count();
        $exchangeRates = $collection->slice(($this->parameters->get('page') - 1) * $pageSize, $pageSize);
        $paginator     = new LengthAwarePaginator($exchangeRates, $count, $pageSize, $this->parameters->get('page'));
        $paginator->setPath(route('api.v1.currencies.cer', [$currency->code]) . $this->buildParams());

        /** @var CurrencyExchangeRateTransformer $transformer */
        $transformer = app(CurrencyExchangeRateTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($exchangeRates, $transformer, 'currency_exchange_rates');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param TransactionCurrency $currency
     *
     * @return JsonResponse
     * @throws FireflyException
     * @codeCoverageIgnore
     */
    public function delete(TransactionCurrency $currency): JsonResponse
    {
        /** @var User $admin */
        $admin = auth()->user();

        if (!$this->userRepository->hasRole($admin, 'owner')) {
            // access denied:
            throw new FireflyException('200005: You need the "owner" role to do this.'); // @codeCoverageIgnore
        }
        if ($this->repository->currencyInUse($currency)) {
            throw new FireflyException('200006: Currency in use.'); // @codeCoverageIgnore
        }
        if ($this->repository->isFallbackCurrency($currency)) {
            throw new FireflyException('200026: Currency is fallback.'); // @codeCoverageIgnore
        }

        $this->repository->destroy($currency);

        return response()->json([], 204);
    }

    /**
     * Disable a currency.
     *
     * @param TransactionCurrency $currency
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function disable(TransactionCurrency $currency): JsonResponse
    {
        // must be unused.
        if ($this->repository->currencyInUse($currency)) {
            return response()->json([], 409);
        }
        $this->repository->disable($currency);
        $manager = $this->getManager();

        $defaultCurrency = app('amount')->getDefaultCurrencyByUser(auth()->user());
        $this->parameters->set('defaultCurrency', $defaultCurrency);

        /** @var CurrencyTransformer $transformer */
        $transformer = app(CurrencyTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new Item($currency, $transformer, 'currencies');

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');

    }

    /**
     * Enable a currency.
     *
     * @param TransactionCurrency $currency
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function enable(TransactionCurrency $currency): JsonResponse
    {
        $this->repository->enable($currency);
        $manager = $this->getManager();

        $defaultCurrency = app('amount')->getDefaultCurrencyByUser(auth()->user());
        $this->parameters->set('defaultCurrency', $defaultCurrency);

        /** @var CurrencyTransformer $transformer */
        $transformer = app(CurrencyTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new Item($currency, $transformer, 'currencies');

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');

    }

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function index(): JsonResponse
    {
        $pageSize   = (int)app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;
        $collection = $this->repository->getAll();
        $count      = $collection->count();
        // slice them:
        $currencies = $collection->slice(($this->parameters->get('page') - 1) * $pageSize, $pageSize);
        $paginator  = new LengthAwarePaginator($currencies, $count, $pageSize, $this->parameters->get('page'));
        $paginator->setPath(route('api.v1.currencies.index') . $this->buildParams());


        $manager         = $this->getManager();
        $defaultCurrency = app('amount')->getDefaultCurrencyByUser(auth()->user());
        $this->parameters->set('defaultCurrency', $defaultCurrency);

        /** @var CurrencyTransformer $transformer */
        $transformer = app(CurrencyTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($currencies, $transformer, 'currencies');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * Make the currency a default currency.
     *
     * @param TransactionCurrency $currency
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function makeDefault(TransactionCurrency $currency): JsonResponse
    {
        $this->repository->enable($currency);

        app('preferences')->set('currencyPreference', $currency->code);
        app('preferences')->mark();

        $manager = $this->getManager();

        $this->parameters->set('defaultCurrency', $currency);

        /** @var CurrencyTransformer $transformer */
        $transformer = app(CurrencyTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new Item($currency, $transformer, 'currencies');

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');

    }

    /**
     * List all recurring transactions.
     *
     * @param TransactionCurrency $currency
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function recurrences(TransactionCurrency $currency): JsonResponse
    {
        $manager = $this->getManager();
        // types to get, page size:
        $pageSize = (int)app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;

        // get list of budgets. Count it and split it.
        /** @var RecurringRepositoryInterface $repository */
        $repository = app(RecurringRepositoryInterface::class);
        $unfiltered = $repository->getAll();

        // filter selection
        $collection = $unfiltered->filter(
            static function (Recurrence $recurrence) use ($currency) {
                /** @var RecurrenceTransaction $transaction */
                foreach ($recurrence->recurrenceTransactions as $transaction) {
                    if ($transaction->transaction_currency_id === $currency->id || $transaction->foreign_currency_id === $currency->id) {
                        return $recurrence;
                    }
                }

                return null;
            }
        );


        $count      = $collection->count();
        $piggyBanks = $collection->slice(($this->parameters->get('page') - 1) * $pageSize, $pageSize);

        // make paginator:
        $paginator = new LengthAwarePaginator($piggyBanks, $count, $pageSize, $this->parameters->get('page'));
        $paginator->setPath(route('api.v1.currencies.recurrences', [$currency->code]) . $this->buildParams());

        /** @var RecurrenceTransformer $transformer */
        $transformer = app(RecurrenceTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($piggyBanks, $transformer, 'recurrences');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');

    }

    /**
     * List all of them.
     *
     * @param TransactionCurrency $currency
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function rules(TransactionCurrency $currency): JsonResponse
    {
        $manager  = $this->getManager();
        $pageSize = (int)app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;

        // get list of budgets. Count it and split it.
        /** @var RuleRepositoryInterface $repository */
        $repository = app(RuleRepositoryInterface::class);
        $unfiltered = $repository->getAll();

        $collection = $unfiltered->filter(
            static function (Rule $rule) use ($currency) {
                /** @var RuleTrigger $trigger */
                foreach ($rule->ruleTriggers as $trigger) {
                    if ('currency_is' === $trigger->trigger_type && $currency->name === $trigger->trigger_value) {
                        return $rule;
                    }
                }

                return null;
            }
        );

        $count = $collection->count();
        $rules = $collection->slice(($this->parameters->get('page') - 1) * $pageSize, $pageSize);

        // make paginator:
        $paginator = new LengthAwarePaginator($rules, $count, $pageSize, $this->parameters->get('page'));
        $paginator->setPath(route('api.v1.rules.index') . $this->buildParams());

        /** @var RuleTransformer $transformer */
        $transformer = app(RuleTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($rules, $transformer, 'rules');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');

    }

    /**
     * Show a currency.
     *
     * @param TransactionCurrency $currency
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function show(TransactionCurrency $currency): JsonResponse
    {
        $manager         = $this->getManager();
        $defaultCurrency = app('amount')->getDefaultCurrencyByUser(auth()->user());
        $this->parameters->set('defaultCurrency', $defaultCurrency);

        /** @var CurrencyTransformer $transformer */
        $transformer = app(CurrencyTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new Item($currency, $transformer, 'currencies');

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * Store new currency.
     *
     * @param CurrencyRequest $request
     *
     * @return JsonResponse
     * @throws FireflyException
     */
    public function store(CurrencyRequest $request): JsonResponse
    {
        $currency = $this->repository->store($request->getAll());
        if (true === $request->boolean('default')) {
            app('preferences')->set('currencyPreference', $currency->code);
            app('preferences')->mark();
        }
        $manager         = $this->getManager();
        $defaultCurrency = app('amount')->getDefaultCurrencyByUser(auth()->user());
        $this->parameters->set('defaultCurrency', $defaultCurrency);

        /** @var CurrencyTransformer $transformer */
        $transformer = app(CurrencyTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new Item($currency, $transformer, 'currencies');

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * Show all transactions.
     *
     * @param Request             $request
     *
     * @param TransactionCurrency $currency
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function transactions(Request $request, TransactionCurrency $currency): JsonResponse
    {
        $pageSize = (int)app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;
        $type     = $request->get('type') ?? 'default';
        $this->parameters->set('type', $type);

        $types   = $this->mapTransactionTypes($this->parameters->get('type'));
        $manager = $this->getManager();

        /** @var User $admin */
        $admin = auth()->user();

        // use new group collector:
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector
            ->setUser($admin)
            // filter on currency.
            ->setCurrency($currency)
            // all info needed for the API:
            ->withAPIInformation()
            // set page size:
            ->setLimit($pageSize)
            // set page to retrieve
            ->setPage($this->parameters->get('page'))
            // set types of transactions to return.
            ->setTypes($types);


        if (null !== $this->parameters->get('start') && null !== $this->parameters->get('end')) {
            $collector->setRange($this->parameters->get('start'), $this->parameters->get('end'));
        }
        $paginator = $collector->getPaginatedGroups();
        $paginator->setPath(route('api.v1.currencies.transactions', [$currency->code]) . $this->buildParams());
        $transactions = $paginator->getCollection();

        /** @var TransactionGroupTransformer $transformer */
        $transformer = app(TransactionGroupTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($transactions, $transformer, 'transactions');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * Update a currency.
     *
     * @param CurrencyRequest     $request
     * @param TransactionCurrency $currency
     *
     * @return JsonResponse
     */
    public function update(CurrencyRequest $request, TransactionCurrency $currency): JsonResponse
    {
        $data     = $request->getAll();
        $currency = $this->repository->update($currency, $data);

        if (true === $request->boolean('default')) {
            app('preferences')->set('currencyPreference', $currency->code);
            app('preferences')->mark();
        }

        $manager = $this->getManager();

        $defaultCurrency = app('amount')->getDefaultCurrencyByUser(auth()->user());
        $this->parameters->set('defaultCurrency', $defaultCurrency);

        /** @var CurrencyTransformer $transformer */
        $transformer = app(CurrencyTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new Item($currency, $transformer, 'currencies');

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');

    }
}
