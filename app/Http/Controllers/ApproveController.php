<?php

namespace FireflyIII\Http\Controllers;
use FireflyIII\Helpers\Collector\GroupCollectorInterface;
use FireflyIII\Repositories\Journal\JournalRepositoryInterface;
use FireflyIII\Support\Http\Controllers\PeriodOverview;

use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Repositories\User\UserRepositoryInterface;
use FireflyIII\Http\Requests\UserFormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Validation\ValidationException;
use FireflyIII\User;
use Carbon\Carbon;
use Log;

/**
 * Class ApproveController.
 *
 */


class ApproveController extends Controller
{
    use PeriodOverview;

    /** @var UserRepositoryInterface */
    private $repository;
    /** @var JournalRepositoryInterface */
    private $journalRepository;

    /**
     * ApproveController constructor.
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        parent::__construct();

        // translations:
        $this->middleware(
            function ($request, $next) {
                // app('view')->share('title', 'Firefly III');
                app('view')->share('title', (string)trans('firefly.approve'));
                app('view')->share('mainTitleIcon', 'fa-fire');
                $this->repository = app(UserRepositoryInterface::class);
                $this->journalRepository = app(JournalRepositoryInterface::class);

                return $next($request);
            }
        );
    }

    /**
     * Index of approve.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    // public function index()
    // {
    //     /** @var User $user */
    //     $user = auth()->user();
    //     $userid = $user->id;
    //     $subTitle = (string)trans('firefly.approve_subtitle');
    //     $approveUsers = $this->repository->approveUsers($userid);
    //     // $categories = $this->repository->categories($user);
    //     $status = $this->repository->transactionStatus();
    //     return view('approve.index', compact('subTitle', 'approveUsers', 'status'));
    // }

    public function index(Request $request, int $userid=0, int $categoryid=0, int $statuid=0, int $expenseid=0, Carbon $start = null, Carbon $end = null)
    {
        // Render Main View
        $user = auth()->user();
        $par_userid = $user->id;
        $subTitle = (string)trans('firefly.approve_subtitle');
        $approveUsers = $this->repository->approveUsers($par_userid);
        $categories = $this->repository->categories($userid);
        $status = $this->repository->transactionStatus();
        $expenses = $this->repository->expenses($userid);

        // Render Table View
        $objectType = 'withdrawal';
        $subTitleIcon = config('firefly.transactionIconsByType.' . $objectType);
        $types        = config('firefly.transactionTypesByType.' . $objectType);
        $page         = (int)$request->get('page');
        $pageSize     = (int)app('preferences')->get('listPageSize', 50)->data;
        if (null === $start) {
            $start = session('start');
            $end   = session('end');
        }
        if (null === $end) {
            $end = session('end'); // @codeCoverageIgnore
        }
        [$start, $end] = $end < $start ? [$end, $start] : [$start, $end];
        $path     = route('approve.approves', [$userid, $categoryid, $statuid, $expenseid, $start->format('Y-m-d'), $end->format('Y-m-d')]);
        $startStr = $start->formatLocalized($this->monthAndDayFormat);
        $endStr   = $end->formatLocalized($this->monthAndDayFormat);
        $subTitle = (string)trans(sprintf('firefly.title_%s_between', $objectType), ['start' => $startStr, 'end' => $endStr]);

        $firstJournal = $this->journalRepository->firstNull();
        $startPeriod  = null === $firstJournal ? new Carbon : $firstJournal->date;
        $endPeriod    = clone $end;
        $periods      = $this->getTransactionPeriodOverview($objectType, $startPeriod, $endPeriod);

        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);

        $collector->setMyUser($userid, $approveUsers)
                  ->setRange($start, $end)
                  ->setTypes($types)
                  ->setLimit($pageSize)
                  ->setPage($page)
                  ->withBudgetInformation()
                  ->withCategoryInformation()
                  ->withAccountInformation()
                  ->withAttachmentInformation();
        if($categoryid != 0)
            $collector->setCategoryId($categoryid);
        if($expenseid != 0)
            $collector->setAccountId($expenseid);
        if($statuid !=0)
        $collector->setStatusId($statuid);

        $groups = $collector->getPaginatedGroups();
        $groups->setPath($path);


        $status = $this->repository->transactionStatus();

        return view(
            'approve.index', 
            compact('subTitle', 'approveUsers', 'categories', 'status', 'expenses', 
                    'subTitle', 'objectType', 'subTitleIcon', 'groups', 'periods', 'start', 'end',
                    'userid', 'categoryid', 'statuid', 'expenseid'
            )
        );

        // return response()->json(
        //     view(
        //         'list.approves',
        //         compact(, 'status')
        //     )->render()
        // );

        // $result = $this->repository->expenses($userid);
        // return response()->json($result->toArray());
        // return response()->json('abscd');
        // return response()->json(view('list.approves'/*, compact('subTitle', 'approveUsers', 'categories', 'status')*/)->render());
    }

    /**
     * Show expenses for approve.
     *
     * @param int $userid
     *
     * @return mixed
     *
     */
    public function expenses(int $userid)
    {
        $result = $this->repository->expenses($userid);
        return response()->json($result->toArray());
    }

    /**
     * Show expenses for approve.
     *
     * @param int $userid
     * @param int $categoryid
     * @param int $statuid
     * @param int $expenseid
     * @param Carbon $start
     * @param Carbon $end
     *
     * @return mixed
     *
     */
    public function approves(Request $request, int $userid=0, int $categoryid=0, int $statuid=0, int $expenseid=0, Carbon $start = null, Carbon $end = null)
    {
        // Render Main View
        $user = auth()->user();
        $par_userid = $user->id;
        $subTitle = (string)trans('firefly.approve_subtitle');
        $approveUsers = $this->repository->approveUsers($par_userid);
        $categories = $this->repository->categories($userid);
        $status = $this->repository->transactionStatus();
        $expenses = $this->repository->expenses($userid);

        // Render Table View
        $objectType = 'withdrawal';
        $subTitleIcon = config('firefly.transactionIconsByType.' . $objectType);
        $types        = config('firefly.transactionTypesByType.' . $objectType);
        $page         = (int)$request->get('page');
        $pageSize     = (int)app('preferences')->get('listPageSize', 50)->data;
        if (null === $start) {
            $start = session('start');
            $end   = session('end');
        }
        if (null === $end) {
            $end = session('end'); // @codeCoverageIgnore
        }
        // return response()->json(
        //     $end
        // );

        
        [$start, $end] = $end < $start ? [$end, $start] : [$start, $end];
        $path     = route('approve.approves', [$userid, $categoryid, $statuid, $expenseid, $start->format('Y-m-d'), $end->format('Y-m-d')]);
        $startStr = $start->formatLocalized($this->monthAndDayFormat);
        $endStr   = $end->formatLocalized($this->monthAndDayFormat);
        $subTitle = (string)trans(sprintf('firefly.title_%s_between', $objectType), ['start' => $startStr, 'end' => $endStr]);

        $firstJournal = $this->journalRepository->firstNull();
        $startPeriod  = null === $firstJournal ? new Carbon : $firstJournal->date;
        $endPeriod    = clone $end;
        $periods      = $this->getTransactionPeriodOverview($objectType, $startPeriod, $endPeriod);

        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);

        $collector->setMyUser($userid, $approveUsers)
                  ->setRange($start, $end)
                  ->setTypes($types)
                  ->setLimit($pageSize)
                  ->setPage($page)
                  ->withBudgetInformation()
                  ->withCategoryInformation()
                  ->withAccountInformation()
                  ->withAttachmentInformation();
        if($categoryid != 0)
            $collector->setCategoryId($categoryid);
        if($expenseid != 0)
            $collector->setAccountId($expenseid);
        if($statuid !=0)
        $collector->setStatusId($statuid);

        $groups = $collector->getPaginatedGroups();
        $groups->setPath($path);


        $status = $this->repository->transactionStatus();

        return view(
            'approve.index', 
            compact('subTitle', 'approveUsers', 'categories', 'status', 'expenses', 
                    'subTitle', 'objectType', 'subTitleIcon', 'groups', 'periods', 'start', 'end',
                    'userid', 'categoryid', 'statuid', 'expenseid'
            )
        );

        // return response()->json(
        //     view(
        //         'list.approves',
        //         compact(, 'status')
        //     )->render()
        // );

        // $result = $this->repository->expenses($userid);
        // return response()->json($result->toArray());
        // return response()->json('abscd');
        // return response()->json(view('list.approves'/*, compact('subTitle', 'approveUsers', 'categories', 'status')*/)->render());
    }

    /**
     * Edit accountant form.
     *
     * @param int $tranid
     * @param int $statuid
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function actions(int $tranid, int $statuid) {
        // app('log')->error();
        $result = $this->repository->updateStatus($tranid, $statuid);
        return response()->json($result);
    }

    /**
     * Edit accountant form.
     *
     * @param User $user
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit(User $user)
    {
        // put previous url in session if not redirect from store (not "return_to_edit").
        if (true !== session('accountants.edit.fromUpdate')) {
            $this->rememberPreviousUri('accountants.edit.uri');
        }
        session()->forget('accountants.edit.fromUpdate');

        $subTitle     = (string)trans('firefly.edit_accountant', ['email' => $user->email]);
        $subTitleIcon = 'fa-user-o';
        $codes        = [
            ''              => (string)trans('firefly.no_block_code'),
            'bounced'       => (string)trans('firefly.block_code_bounced'),
            'expired'       => (string)trans('firefly.block_code_expired'),
            'email_changed' => (string)trans('firefly.block_code_email_changed'),
        ];

        return view('accountants.edit', compact('user', 'subTitle', 'subTitleIcon', 'codes'));
    }


    /**
     * Update single accountant.
     *
     * @param UserFormRequest         $request
     * @param User                    $user
     *
     * @return $this|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(UserFormRequest $request, User $user)
    {
        Log::debug('Actually here');
        $data = $request->getUserData();

        // update password
        if ('' !== $data['password']) {
            $this->repository->changePassword($user, $data['password']);
        }
        $par_userid = auth()->user()->id;
        $data['isAccountant'] = 1;

        $this->repository->changeStatus($user, $data['isAccountant'], $data['blocked'], $data['blocked_code']);
        $this->repository->updateEmail($user, $data['email']);

        session()->flash('success', (string)trans('firefly.updated_user', ['email' => $user->email]));
        app('preferences')->mark();
        $redirect = redirect($this->getPreviousUri('accountants.edit.uri'));
        if (1 === (int)$request->get('return_to_edit')) {
            // @codeCoverageIgnoreStart
            session()->put('accountants.edit.fromUpdate', true);

            $redirect = redirect(route('accountants.edit', [$user->id]))->withInput(['return_to_edit' => 1]);
            // @codeCoverageIgnoreEnd
        }

        // redirect to previous URL.
        return $redirect;
    }    


    /**
     * Create a new accountant.
     *
     * @param Request $request
     * @param string|null $objectType
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create(Request $request)
    {
        $subTitle     = (string)trans('firefly.create_accountant');
        $subTitleIcon = 'fa-user-o';

        // put previous url in session if not redirect from store (not "create another").
        if (true !== session('accountants.create.fromStore')) {
            $this->rememberPreviousUri('accountants.create.uri');
        }
        $request->session()->forget('accountants.create.fromStore');
        Log::channel('audit')->info('Creating new accountant.');

        return view('accountants.create', compact('subTitle', 'subTitleIcon'));
    }    

    /**
     * Store the new accountant.
     *
     * @param AccountantFormRequest $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(AccountantFormRequest $request)
    {
        $data = $request->getAccountantData();
        $par_userid = auth()->user()->id;
        $data['isAccountant'] = $par_userid;
        list($user, $exists) = $this->repository->store_accountant($data, $par_userid);
        
        # IF already user exists not accountant, return error
        if($exists == 0) {
            throw ValidationException::withMessages(['email' => (string)trans('validation.email_exists_user')]);
        } else if($exists == 1) {
            $request->session()->flash('success', (string)trans('firefly.stored_exists_accountant', ['name' => $user->name]));
        } else if($exists == 2) {
            $request->session()->flash('success', (string)trans('firefly.stored_new_accountant', ['name' => $user->name]));
        }
        app('preferences')->mark();
        // Log::channel('audit')->info('Stored new account.', $data);
        // redirect to previous URL.
        $redirect = redirect($this->getPreviousUri('accountants.create.uri'));
        if (1 === (int)$request->get('create_another')) {
            // set value so create routine will not overwrite URL:
            $request->session()->put('accountants.create.fromStore', true);
            $redirect = redirect(route('accountants.create'))->withInput();
        }
        return $redirect;
    }

    /**
     * Delete a user.
     *
     * @param Accountant $user
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function delete(User $user)
    {
        $subTitle = (string)trans('firefly.delete_accountant', ['email' => $user->email]);

        return view('accountants.delete', compact('user', 'subTitle'));
    }


    /**
     * Destroy a user.
     *
     * @param Accountant $user
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy(User $user)
    {
        $par_userid = auth()->user()->id;
        $this->repository->destroy_accountant($user, $par_userid);
        session()->flash('success', (string)trans('firefly.accountant_deleted'));
        return redirect(route('accountants.index'));
    }

}
