<?php
/**
 * AutoCompleteController.php
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

namespace FireflyIII\Http\Controllers\Json;

use Carbon\Carbon;
use FireflyIII\Http\Controllers\Controller;
use FireflyIII\Models\Account;
use FireflyIII\Models\AccountType;
use FireflyIII\Models\PiggyBank;
use FireflyIII\Models\TransactionCurrency;
use FireflyIII\Repositories\Account\AccountRepositoryInterface;
use FireflyIII\Repositories\Bill\BillRepositoryInterface;
use FireflyIII\Repositories\Budget\BudgetRepositoryInterface;
use FireflyIII\Repositories\Category\CategoryRepositoryInterface;
use FireflyIII\Repositories\Currency\CurrencyRepositoryInterface;
use FireflyIII\Repositories\Journal\JournalRepositoryInterface;
use FireflyIII\Repositories\PiggyBank\PiggyBankRepositoryInterface;
use FireflyIII\Repositories\Tag\TagRepositoryInterface;
use FireflyIII\Repositories\TransactionGroup\TransactionGroupRepositoryInterface;
use FireflyIII\Repositories\TransactionType\TransactionTypeRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;

/**
 * Class AutoCompleteController.
 *
 * TODO autocomplete for transaction types.
 *
 */
class AutoCompleteController extends Controller
{

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function accounts(Request $request): JsonResponse
    {
        $accountTypes = explode(',', $request->get('types') ?? '');
        $search       = $request->get('search');
        /** @var AccountRepositoryInterface $repository */
        $repository = app(AccountRepositoryInterface::class);

        // filter the account types:
        $allowedAccountTypes  = [AccountType::ASSET, AccountType::EXPENSE, AccountType::REVENUE, AccountType::LOAN, AccountType::DEBT, AccountType::MORTGAGE,];
        $balanceTypes         = [AccountType::ASSET, AccountType::LOAN, AccountType::DEBT, AccountType::MORTGAGE,];
        $filteredAccountTypes = [];
        foreach ($accountTypes as $type) {
            if (in_array($type, $allowedAccountTypes, true)) {
                $filteredAccountTypes[] = $type;
            }
        }
        if (0 === count($filteredAccountTypes)) {
            $filteredAccountTypes = $allowedAccountTypes;
        }
        Log::debug(sprintf('Now in accounts("%s"). Filtering results.', $search), $filteredAccountTypes);

        $return          = [];
        $result          = $repository->searchAccount((string)$search, $filteredAccountTypes);
        $defaultCurrency = app('amount')->getDefaultCurrency();

        /** @var Account $account */
        foreach ($result as $account) {
            $nameWithBalance = $account->name;
            $currency        = $repository->getAccountCurrency($account) ?? $defaultCurrency;

            if (in_array($account->accountType->type, $balanceTypes, true)) {
                $balance         = app('steam')->balance($account, new Carbon);
                $nameWithBalance = sprintf('%s (%s)', $account->name, app('amount')->formatAnything($currency, $balance, false));
            }

            $return[] = [
                'id'                      => $account->id,
                'name'                    => $account->name,
                'name_with_balance'       => $nameWithBalance,
                'type'                    => $account->accountType->type,
                'currency_id'             => $currency->id,
                'currency_name'           => $currency->name,
                'currency_code'           => $currency->code,
                'currency_decimal_places' => $currency->decimal_places,
            ];
        }


        return response()->json($return);
    }

    /**
     * Searches in the titles of all transaction journals.
     * The result is limited to the top 15 unique results.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function allJournals(Request $request): JsonResponse
    {
        $search = (string)$request->get('search');
        /** @var JournalRepositoryInterface $repository */
        $repository = app(JournalRepositoryInterface::class);
        $result     = $repository->searchJournalDescriptions($search);

        // limit and unique
        $filtered = $result->unique('description');
        $limited  = $filtered->slice(0, 15);
        $array    = $limited->toArray();
        // duplicate 'description' value into 'name':
        $array = array_map(
            static function (array $journal) {
                $journal['name'] = $journal['description'];

                return $journal;
            }, $array
        );

        return response()->json(array_values($array));
    }

    /**
     * Searches in the titles of all transaction journals.
     * The result is limited to the top 15 unique results.
     *
     * If the query is numeric, it will append the journal with that particular ID.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function allJournalsWithID(Request $request): JsonResponse
    {
        $search = (string)$request->get('search');
        /** @var JournalRepositoryInterface $repository */
        $repository = app(JournalRepositoryInterface::class);

        /** @var TransactionGroupRepositoryInterface $groupRepos */
        $groupRepos = app(TransactionGroupRepositoryInterface::class);

        $result = $repository->searchJournalDescriptions($search);
        $array  = [];
        if (is_numeric($search)) {
            // search for group, not journal.
            $firstResult = $groupRepos->find((int)$search);
            if (null !== $firstResult) {
                // group may contain multiple journals, each a result:
                foreach ($firstResult->transactionJournals as $journal) {
                    $array[] = $journal->toArray();
                }
            }
        }
        // if not numeric, search ahead!

        // limit and unique
        $limited = $result->slice(0, 15);
        $array   = array_merge($array, $limited->toArray());
        foreach ($array as $index => $item) {
            // give another key for consistency
            $array[$index]['name'] = sprintf('#%d: %s', $item['transaction_group_id'], $item['description']);
        }


        return response()->json($array);
    }

    /**
     * An auto-complete specifically for asset accounts and liabilities, used when mass updating and for rules mostly.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function assetAccounts(Request $request): JsonResponse
    {
        $search = $request->get('search');
        /** @var AccountRepositoryInterface $repository */
        $repository = app(AccountRepositoryInterface::class);

        // filter the account types:
        $allowedAccountTypes = [AccountType::ASSET, AccountType::LOAN, AccountType::DEBT, AccountType::MORTGAGE];
        Log::debug(sprintf('Now in expenseAccounts(%s). Filtering results.', $search), $allowedAccountTypes);

        $return = [];
        $result = $repository->searchAccount((string)$search, $allowedAccountTypes);

        /** @var Account $account */
        foreach ($result as $account) {
            $return[] = [
                'id'   => $account->id,
                'name' => $account->name,
                'type' => $account->accountType->type,
            ];
        }

        return response()->json($return);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function bills(Request $request): JsonResponse
    {
        $query = (string)$request->get('search');
        /** @var BillRepositoryInterface $repository */
        $repository = app(BillRepositoryInterface::class);
        $result     = $repository->searchBill($query);

        return response()->json($result->toArray());
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function budgets(Request $request): JsonResponse
    {
        $search = (string)$request->get('search');
        /** @var BudgetRepositoryInterface $repository */
        $repository = app(BudgetRepositoryInterface::class);
        $result     = $repository->searchBudget($search);

        return response()->json($result->toArray());
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function categories(Request $request): JsonResponse
    {
        $query = (string)$request->get('search');
        /** @var CategoryRepositoryInterface $repository */
        $repository = app(CategoryRepositoryInterface::class);
        $result     = $repository->searchCategory($query);

        return response()->json($result->toArray());
    }

    /**
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function currencies(): JsonResponse
    {
        /** @var CurrencyRepositoryInterface $repository */
        $repository = app(CurrencyRepositoryInterface::class);
        $return     = [];
        $collection = $repository->getAll();

        /** @var TransactionCurrency $currency */
        foreach ($collection as $currency) {
            $return[] = [
                'id'             => $currency->id,
                'name'           => $currency->name,
                'code'           => $currency->code,
                'symbol'         => $currency->symbol,
                'enabled'        => $currency->enabled,
                'decimal_places' => $currency->decimal_places,
            ];
        }

        return response()->json($return);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function currencyNames(Request $request): JsonResponse
    {
        $query = (string)$request->get('search');
        /** @var CurrencyRepositoryInterface $repository */
        $repository = app(CurrencyRepositoryInterface::class);
        $result     = $repository->searchCurrency($query)->toArray();
        foreach ($result as $index => $item) {
            $result[$index]['name'] = sprintf('%s (%s)', $item['name'], $item['code']);
        }

        return response()->json($result);
    }

    /**
     * An auto-complete specifically for expense accounts, used when mass updating mostly.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function expenseAccounts(Request $request): JsonResponse
    {
        $search = $request->get('search');
        /** @var AccountRepositoryInterface $repository */
        $repository = app(AccountRepositoryInterface::class);

        // filter the account types:
        $allowedAccountTypes = [AccountType::EXPENSE];
        Log::debug(sprintf('Now in expenseAccounts(%s). Filtering results.', $search), $allowedAccountTypes);

        $return = [];
        $result = $repository->searchAccount((string)$search, $allowedAccountTypes);

        /** @var Account $account */
        foreach ($result as $account) {
            $return[] = [
                'id'   => $account->id,
                'name' => $account->name,
                'type' => $account->accountType->type,
            ];
        }

        return response()->json($return);
    }

    /**
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function piggyBanks(): JsonResponse
    {
        /** @var PiggyBankRepositoryInterface $repository */
        $repository = app(PiggyBankRepositoryInterface::class);

        /** @var AccountRepositoryInterface $accountRepos */
        $accountRepos = app(AccountRepositoryInterface::class);

        $piggies         = $repository->getPiggyBanks();
        $defaultCurrency = \Amount::getDefaultCurrency();
        $response        = [];
        /** @var PiggyBank $piggy */
        foreach ($piggies as $piggy) {
            $currency                = $accountRepos->getAccountCurrency($piggy->account) ?? $defaultCurrency;
            $currentAmount           = $repository->getRepetition($piggy)->currentamount ?? '0';
            $piggy->name_with_amount = sprintf(
                '%s (%s / %s)',
                $piggy->name,
                app('amount')->formatAnything($currency, $currentAmount, false),
                app('amount')->formatAnything($currency, $piggy->targetamount, false),
            );
            $response[]              = $piggy->toArray();
        }

        return response()->json($response);
    }

    /**
     * An auto-complete specifically for revenue accounts, used when converting transactions mostly.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function revenueAccounts(Request $request): JsonResponse
    {
        $search = $request->get('search');
        /** @var AccountRepositoryInterface $repository */
        $repository = app(AccountRepositoryInterface::class);

        // filter the account types:
        $allowedAccountTypes = [AccountType::REVENUE];
        Log::debug('Now in revenueAccounts(). Filtering results.', $allowedAccountTypes);

        $return = [];
        $result = $repository->searchAccount((string)$search, $allowedAccountTypes);

        /** @var Account $account */
        foreach ($result as $account) {
            $return[] = [
                'id'   => $account->id,
                'name' => $account->name,
                'type' => $account->accountType->type,
            ];
        }

        return response()->json($return);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function tags(Request $request): JsonResponse
    {
        $search = (string)$request->get('search');
        /** @var TagRepositoryInterface $repository */
        $repository = app(TagRepositoryInterface::class);
        $result     = $repository->searchTags($search);
        $array      = $result->toArray();
        foreach ($array as $index => $item) {
            // rename field for consistency.
            $array[$index]['name'] = $item['tag'];
        }

        return response()->json($array);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function transactionTypes(Request $request): JsonResponse
    {
        $query = (string)$request->get('search');
        /** @var TransactionTypeRepositoryInterface $repository */
        $repository = app(TransactionTypeRepositoryInterface::class);
        $array      = $repository->searchTypes($query)->toArray();

        foreach ($array as $index => $item) {
            // different key for consistency.
            $array[$index]['name'] = $item['type'];
        }

        return response()->json($array);
    }

}
