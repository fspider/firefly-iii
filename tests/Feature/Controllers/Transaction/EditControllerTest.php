<?php
/**
 * EditControllerTest.php
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

namespace Tests\Feature\Controllers\Transaction;


use FireflyIII\Repositories\Account\AccountRepositoryInterface;
use FireflyIII\Repositories\User\UserRepositoryInterface;
use Log;
use Tests\TestCase;

/**
 * Class EditControllerTest
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class EditControllerTest extends TestCase
{
    /**
     *
     */
    public function setUp(): void
    {
        parent::setUp();
        Log::info(sprintf('Now in %s.', get_class($this)));
    }

    public function testEdit(): void
    {
        $group        = $this->getRandomWithdrawalGroup();
        $account      = $this->getRandomAsset();
        $accountRepos = $this->mock(AccountRepositoryInterface::class);
        $userRepos = $this->mock(UserRepositoryInterface::class);

        $this->mockDefaultSession();
        $userRepos->shouldReceive('hasRole')->atLeast()->once()->andReturn(true);
        $accountRepos->shouldReceive('getCashAccount')->atLeast()->once()->andReturn($account);


        $this->be($this->user());
        $response = $this->get(route('transactions.edit', [$group->id]));
        $response->assertStatus(200);
    }
}
