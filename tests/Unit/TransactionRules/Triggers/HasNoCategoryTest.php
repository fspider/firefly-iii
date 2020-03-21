<?php
/**
 * HasNoCategoryTest.php
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

use FireflyIII\Models\Transaction;
use FireflyIII\Models\TransactionJournal;
use FireflyIII\TransactionRules\Triggers\HasNoCategory;
use Tests\TestCase;

/**
 * Class HasNoCategoryTest
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class HasNoCategoryTest extends TestCase
{
    /**
     * @covers \FireflyIII\TransactionRules\Triggers\HasNoCategory
     */
    public function testTriggeredCategory(): void
    {
        $journal  = $this->getRandomWithdrawal();
        $category = $this->getRandomCategory();;
        $journal->categories()->detach();
        $journal->categories()->save($category);
        $this->assertEquals(1, $journal->categories()->count());

        $trigger = HasNoCategory::makeFromStrings('', false);
        $result  = $trigger->triggered($journal);
        $this->assertFalse($result);
    }

    /**
     * @covers \FireflyIII\TransactionRules\Triggers\HasNoCategory
     */
    public function testTriggeredNoCategory(): void
    {
        $journal = $this->getRandomWithdrawal();
        $journal->categories()->detach();

        // also detach transactions:
        /** @var Transaction $transaction */
        foreach ($journal->transactions as $transaction) {
            $transaction->categories()->detach();
            $this->assertEquals(0, $transaction->categories()->count());
        }

        $this->assertEquals(0, $journal->categories()->count());

        $trigger = HasNoCategory::makeFromStrings('', false);
        $result  = $trigger->triggered($journal);
        $this->assertTrue($result);
    }

    /**
     * @covers \FireflyIII\TransactionRules\Triggers\HasNoCategory
     */
    public function testTriggeredTransaction(): void
    {
        $withdrawal  = $this->getRandomWithdrawal();
        $transaction = $withdrawal->transactions()->first();
        $category    = $withdrawal->user->categories()->first();

        $withdrawal->categories()->detach();
        $transaction->categories()->sync([$category->id]);
        $this->assertEquals(0, $withdrawal->categories()->count());
        $this->assertEquals(1, $transaction->categories()->count());

        $trigger = HasNoCategory::makeFromStrings('', false);
        $result  = $trigger->triggered($withdrawal);
        $this->assertFalse($result);
    }

    /**
     * @covers \FireflyIII\TransactionRules\Triggers\HasNoCategory
     */
    public function testWillMatchEverythingNull(): void
    {
        $value  = null;
        $result = HasNoCategory::willMatchEverything($value);
        $this->assertFalse($result);
    }
}
