<?php
/**
 * CreateController.php
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

namespace FireflyIII\Http\Controllers\Transaction;


use FireflyIII\Http\Controllers\Controller;
use FireflyIII\Models\TransactionGroup;
use FireflyIII\Repositories\Account\AccountRepositoryInterface;
use FireflyIII\Services\Internal\Update\GroupCloneService;

/**
 * Class CreateController
 */
class CreateController extends Controller
{
    /**
     * CreateController constructor.
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        parent::__construct();

        $maxFileSize = app('steam')->phpBytes(ini_get('upload_max_filesize'));
        $maxPostSize = app('steam')->phpBytes(ini_get('post_max_size'));
        $uploadSize  = min($maxFileSize, $maxPostSize);
        app('view')->share('uploadSize', $uploadSize);
        $this->middleware(
            static function ($request, $next) {

                app('view')->share('title', (string)trans('firefly.transactions'));
                app('view')->share('mainTitleIcon', 'fa-repeat');

                return $next($request);
            }
        );
    }

    /**
     * @param TransactionGroup $group
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function cloneGroup(TransactionGroup $group)
    {

        /** @var GroupCloneService $service */
        $service  = app(GroupCloneService::class);
        $newGroup = $service->cloneGroup($group);

        app('preferences')->mark();

        $title = $newGroup->title ?? $newGroup->transactionJournals->first()->description;

        session()->flash('success', trans('firefly.stored_journal', ['description' => $title]));

        return redirect(route('transactions.show', [$newGroup->id]));
    }

    /**
     * Create a new transaction group.
     *
     * @param string|null objectType
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create(?string $objectType)
    {
        app('preferences')->mark();

        $sourceId      = (int)request()->get('source');
        $destinationId = (int)request()->get('destination');

        /** @var AccountRepositoryInterface $repository */
        $repository           = app(AccountRepositoryInterface::class);
        $cash                 = $repository->getCashAccount();
        $preFilled            = session()->has('preFilled') ? session('preFilled') : [];
        $subTitle             = (string)trans('breadcrumbs.create_new_transaction');
        $subTitleIcon         = 'fa-plus';
        $optionalFields       = app('preferences')->get('transaction_journal_optional_fields', [])->data;
        $allowedOpposingTypes = config('firefly.allowed_opposing_types');
        $accountToTypes       = config('firefly.account_to_transaction');
        $defaultCurrency      = app('amount')->getDefaultCurrency();
        $previousUri          = $this->rememberPreviousUri('transactions.create.uri');
        $parts                = parse_url($previousUri);
        $search               = sprintf('?%s', $parts['query'] ?? '');
        $previousUri          = str_replace($search, '', $previousUri);

        session()->put('preFilled', $preFilled);


        return view(
            'transactions.create', compact(
                                     'subTitleIcon', 'cash', 'objectType', 'subTitle', 'defaultCurrency', 'previousUri', 'optionalFields', 'preFilled',
                                     'allowedOpposingTypes',
                                     'accountToTypes','sourceId','destinationId'
                                 )
        );
    }
}
