<?php
/**
 * TelemetryController.php
 * Copyright (c) 2020 thegrumpydictator@gmail.com
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

namespace FireflyIII\Http\Controllers\Admin;


use FireflyIII\Http\Controllers\Controller;

/**
 * Class TelemetryController
 */
class TelemetryController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(
            static function ($request, $next) {
                app('view')->share('title', (string)trans('firefly.administration'));
                app('view')->share('mainTitleIcon', 'fa-hand-spock-o');

                return $next($request);
            }
        );
    }

    /**
     * @return string
     */
    public function delete()
    {
        session()->flash('info', 'No telemetry to delete. Does not work yet.');

        return redirect(route('admin.telemetry.index'));
    }

    /**
     *
     */
    public function index()
    {
        app('view')->share('subTitleIcon', 'fa-eye');
        app('view')->share('subTitle', (string)trans('firefly.telemetry_admin_index'));
        $version = config('firefly.version');
        $enabled = config('firefly.telemetry', false);
        $count   = 1;

        return view('admin.telemetry.index', compact('version', 'enabled', 'count'));
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function view()
    {
        return view('admin.telemetry.view');
    }

}