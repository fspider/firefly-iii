<?php

namespace FireflyIII\Http\Controllers;

use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Repositories\User\UserRepositoryInterface;
use FireflyIII\Http\Requests\UserFormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as LaravelResponse;
use FireflyIII\User;

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
        $accountants = $this->repository->accountants();
        // $set = $set->each(
        //     function (Accountant $accountant) {
        //         return $accountant;
        //     }
        // );

        return view('accountants.index', compact('accountants'));
    }

}
