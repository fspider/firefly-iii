<?php
/**
 * Binder.php
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

namespace FireflyIII\Http\Middleware;

use Closure;
use FireflyIII\Support\Domain;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Routing\Route;

/**
 * Class Binder
 */
class Binder
{
    /**
     * The authentication factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;
    /**
     * The binders.
     *
     * @var array
     */
    protected $binders = [];

    /**
     * Binder constructor.
     *
     * @param  \Illuminate\Contracts\Auth\Factory $auth
     */
    public function __construct(Auth $auth)
    {
        $this->binders = Domain::getBindables();
        $this->auth    = $auth;
    }

    /** @noinspection PhpUnusedParameterInspection */
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     *
     * @return mixed
     *
     */
    public function handle($request, Closure $next)
    {
        foreach ($request->route()->parameters() as $key => $value) {
            if (isset($this->binders[$key])) {
                $boundObject = $this->performBinding($key, $value, $request->route());
                $request->route()->setParameter($key, $boundObject);
            }
        }

        return $next($request);
    }

    /**
     * Do the binding.
     *
     * @param $key
     * @param $value
     * @param $route
     *
     * @return mixed
     */
    private function performBinding(string $key, string $value, Route $route)
    {
        $class = $this->binders[$key];

        return $class::routeBinder($value, $route);
    }
}
