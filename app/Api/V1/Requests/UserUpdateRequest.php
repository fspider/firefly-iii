<?php

/**
 * UserUpdateRequest.php
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

namespace FireflyIII\Api\V1\Requests;

use FireflyIII\Repositories\User\UserRepositoryInterface;
use FireflyIII\Rules\IsBoolean;
use FireflyIII\User;


/**
 * Class UserUpdateRequest
 */
class UserUpdateRequest extends Request
{
    /**
     * Authorize logged in users.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $result = false;
        // Only allow authenticated users
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            /** @var UserRepositoryInterface $repository */
            $repository = app(UserRepositoryInterface::class);

            if ($repository->hasRole($user, 'owner')) {
                $result = true; // @codeCoverageIgnore
            }
        }

        return $result;
    }

    /**
     * Get all data from the request.
     *
     * @return array
     */
    public function getAll(): array
    {
        $blocked = false;
        if (null !== $this->get('blocked')) {
            $blocked = $this->boolean('blocked');
        }
        $data = [
            'email'        => $this->string('email'),
            'blocked'      => $blocked,
            'blocked_code' => $this->string('blocked_code'),
            'role'         => $this->string('role'),
        ];

        return $data;
    }

    /**
     * The rules that the incoming request must be matched against.
     *
     * @return array
     */
    public function rules(): array
    {
        $user  = $this->route()->parameter('user');
        $rules = [
            'email'        => sprintf('email|unique:users,email,%d', $user->id),
            'blocked'      => [new IsBoolean],
            'blocked_code' => 'in:email_changed',
            'role'         => 'in:owner,demo,',
        ];

        return $rules;
    }

}
