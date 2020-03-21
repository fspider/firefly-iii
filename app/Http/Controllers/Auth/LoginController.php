<?php
/**
 * LoginController.php
 * Copyright (c) 2020 james@firefly-iii.org
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

namespace FireflyIII\Http\Controllers\Auth;

use Adldap;
use DB;
use FireflyIII\Http\Controllers\Controller;
use FireflyIII\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Log;

/**
 * Class LoginController
 *
 * This controller handles authenticating users for the application and
 * redirecting them to your home screen. The controller uses a trait
 * to conveniently provide its functionality to your applications.
 *
 * @codeCoverageIgnore
 */
class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware('guest')->except('logout');
    }


    /**
     * Handle a login request to the application.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        Log::channel('audit')->info(sprintf('User is trying to login using "%s"', $request->get('email')));
        Log::info(sprintf('User is trying to login.'));
        if ('ldap' === config('auth.providers.users.driver')) {
            /**
             * Temporary bug fix for something that doesn't seem to work in
             * AdLdap.
             */
            $schema = config('ldap.connections.default.schema');

            /** @var Adldap\Connections\Provider $provider */
            Adldap::getProvider('default')->setSchema(new $schema);
        }

        $this->validateLogin($request);

        /** Copied directly from AuthenticatesUsers, but with logging added: */
        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if (method_exists($this, 'hasTooManyLoginAttempts') && $this->hasTooManyLoginAttempts($request)) {
            Log::channel('audit')->info(sprintf('Login for user "%s" was locked out.', $request->get('email')));
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        /** Copied directly from AuthenticatesUsers, but with logging added: */
        if ($this->attemptLogin($request)) {
            Log::channel('audit')->info(sprintf('User "%s" has been logged in.', $request->get('email')));
            Log::debug(sprintf('Redirect after login is %s.', $this->redirectPath()));

            return $this->sendLoginResponse($request);
        }

        /** Copied directly from AuthenticatesUsers, but with logging added: */
        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);
        Log::channel('audit')->info(sprintf('Login attempt for user "%s" failed.', $request->get('email')));

        return $this->sendFailedLoginResponse($request);
    }

    /**
     * Show the application's login form.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function showLoginForm(Request $request)
    {
        $count         = DB::table('users')->count();
        $loginProvider = config('firefly.login_provider');
        $title         = (string)trans('firefly.login_page_title');
        if (0 === $count && 'eloquent' === $loginProvider) {
            return redirect(route('register')); // @codeCoverageIgnore
        }

        // is allowed to?
        $singleUserMode    = app('fireflyconfig')->get('single_user_mode', config('firefly.configuration.single_user_mode'))->data;
        $allowRegistration = true;
        $allowReset        = true;
        if (true === $singleUserMode && $count > 0) {
            $allowRegistration = false;
        }

        // single user mode is ignored when the user is not using eloquent:
        if ('eloquent' !== $loginProvider) {
            $allowRegistration = false;
            $allowReset        = false;
        }

        $email    = $request->old('email');
        $remember = $request->old('remember');

        return view('auth.login', compact('allowRegistration', 'email', 'remember', 'allowReset', 'title'));
    }

    /**
     * Get the failed login response instance.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws ValidationException
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        $exception             = ValidationException::withMessages(
            [
                $this->username() => [trans('auth.failed')],
            ]
        );
        $exception->redirectTo = route('login');

        throw $exception;
    }

}
