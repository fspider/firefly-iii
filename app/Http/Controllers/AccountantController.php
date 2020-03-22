<?php

namespace FireflyIII\Http\Controllers;

use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Repositories\User\UserRepositoryInterface;
use FireflyIII\Http\Requests\UserFormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as LaravelResponse;
use FireflyIII\User;
use Log;

/**
 * Class AccountantController.
 *
 */


class AccountantController extends Controller
{
    /** @var UserRepositoryInterface */
    private $repository;

    /**
     * AccountantController constructor.
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        parent::__construct();

        // translations:
        $this->middleware(
            function ($request, $next) {
                app('view')->share('mainTitleIcon', 'fa-ticket');
                app('view')->share('title', (string)trans('firefly.accountants'));
                $this->repository = app(UserRepositoryInterface::class);

                return $next($request);
            }
        );
    }

    /**
     * Index of all accountants.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        /** @var User $user */
        $user = auth()->user();
        $userid = $user->id;
        $accountants = $this->repository->accountants($userid);
        // $set = $set->each(
        //     function (Accountant $accountant) {
        //         return $accountant;
        //     }
        // );

        return view('accountants.index', compact('accountants'));
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
        $data['isAccountant'] = $par_userid;

        $this->repository->changeStatus($user, $data['isAccountant'], $data['blocked'], $data['blocked_code']);
        $this->repository->updateEmail($user, $data['email']);

        session()->flash('success', (string)trans('firefly.updated_user', ['email' => $user->email]));
        app('preferences')->mark();
        $redirect = redirect($this->getPreviousUri('users.edit.uri'));
        if (1 === (int)$request->get('return_to_edit')) {
            // @codeCoverageIgnoreStart
            session()->put('accountants.edit.fromUpdate', true);

            $redirect = redirect(route('accountants.edit', [$user->id]))->withInput(['return_to_edit' => 1]);
            // @codeCoverageIgnoreEnd
        }

        // redirect to previous URL.
        return $redirect;
    }    
}
