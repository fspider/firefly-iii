<?php
/**
 * RemoveBillsTest.php
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

namespace Tests\Unit\Console\Commands\Correction;


use FireflyIII\Models\TransactionJournal;
use Log;
use Tests\TestCase;

/**
 * Class RemoveBillsTest
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class RemoveBillsTest extends TestCase
{

    /**
     *
     */
    public function setUp(): void
    {
        parent::setUp();
        Log::info(sprintf('Now in %s.', get_class($this)));
    }

    /**
     * @covers \FireflyIII\Console\Commands\Correction\RemoveBills
     */
    public function testHandle(): void
    {
        // assume there's nothing to fix.
        $this->artisan('firefly-iii:remove-bills')
             ->expectsOutput('All transaction journals have correct bill information.')
             ->assertExitCode(0);

        // dont verify anything
    }

    /**
     * @covers \FireflyIII\Console\Commands\Correction\RemoveBills
     */
    public function testHandleWithdrawal(): void
    {
        $bill    = $this->user()->bills()->first();
        $journal = $this->getRandomDeposit();

        $journal->bill_id = $bill->id;
        $journal->save();

        $this->artisan('firefly-iii:remove-bills')
             ->expectsOutput(sprintf('Transaction journal #%d should not be linked to bill #%d.', $journal->id, $bill->id))
             ->assertExitCode(0);

        // verify change
        $this->assertCount(0, TransactionJournal::where('id', $journal->id)->whereNotNull('bill_id')->get());
    }
}
