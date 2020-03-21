<?php
/**
 * AmountExactlyTest.php
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

namespace Tests\Unit\TransactionRules\Triggers;

use FireflyIII\Models\TransactionJournal;
use FireflyIII\Repositories\Journal\JournalRepositoryInterface;
use FireflyIII\TransactionRules\Triggers\AmountExactly;
use Tests\TestCase;

/**
 * Class AmountExactlyTest
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class AmountExactlyTest extends TestCase
{
    /**
     * @covers \FireflyIII\TransactionRules\Triggers\AmountExactly
     */
    public function testTriggeredExact(): void
    {
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $journalRepos->shouldReceive('setUser');
        $journalRepos->shouldReceive('getJournalTotal')->andReturn('12.34');
        $journal                     = new TransactionJournal;
        $journal->user               = $this->user();
        $journal->destination_amount = '12.34';
        $trigger                     = AmountExactly::makeFromStrings('12.340', false);
        $result                      = $trigger->triggered($journal);
        $this->assertTrue($result);
    }

    /**
     * @covers \FireflyIII\TransactionRules\Triggers\AmountExactly
     */
    public function testTriggeredNotExact(): void
    {
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $journalRepos->shouldReceive('setUser');
        $journalRepos->shouldReceive('getJournalTotal')->andReturn('12.34');
        $journal                     = new TransactionJournal;
        $journal->user               = $this->user();
        $journal->destination_amount = '12.35';
        $trigger                     = AmountExactly::makeFromStrings('12.340', false);
        $result                      = $trigger->triggered($journal);
        $this->assertFalse($result);
    }

    /**
     * @covers \FireflyIII\TransactionRules\Triggers\AmountExactly
     */
    public function testWillMatchEverythingNotNull(): void
    {
        $value  = 'x';
        $result = AmountExactly::willMatchEverything($value);
        $this->assertFalse($result);
    }

    /**
     * @covers \FireflyIII\TransactionRules\Triggers\AmountExactly
     */
    public function testWillMatchEverythingNull(): void
    {
        $value  = null;
        $result = AmountExactly::willMatchEverything($value);
        $this->assertTrue($result);
    }
}
