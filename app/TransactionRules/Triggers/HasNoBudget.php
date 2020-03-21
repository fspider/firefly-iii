<?php
/**
 * HasNoBudget.php
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

namespace FireflyIII\TransactionRules\Triggers;

use FireflyIII\Models\Transaction;
use FireflyIII\Models\TransactionJournal;
use Log;

/**
 * Class HasNoBudget.
 */
final class HasNoBudget extends AbstractTrigger implements TriggerInterface
{
    /**
     * A trigger is said to "match anything", or match any given transaction,
     * when the trigger value is very vague or has no restrictions. Easy examples
     * are the "AmountMore"-trigger combined with an amount of 0: any given transaction
     * has an amount of more than zero! Other examples are all the "Description"-triggers
     * which have hard time handling empty trigger values such as "" or "*" (wild cards).
     *
     * If the user tries to create such a trigger, this method MUST return true so Firefly III
     * can stop the storing / updating the trigger. If the trigger is in any way restrictive
     * (even if it will still include 99.9% of the users transactions), this method MUST return
     * false.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function willMatchEverything($value = null): bool
    {
        return false;
    }

    /**
     * Returns true when journal has no budget
     *
     * @param TransactionJournal $journal
     *
     * @return bool
     */
    public function triggered(TransactionJournal $journal): bool
    {
        $count = $journal->budgets()->count();

        // perhaps transactions have budget?
        /** @var Transaction $transaction */
        foreach ($journal->transactions()->get() as $transaction) {
            $count += $transaction->budgets()->count();
        }
        if (0 === $count) {
            Log::debug(sprintf('RuleTrigger HasNoBudget for journal #%d: count is %d, return true.', $journal->id, $count));

            return true;
        }

        Log::debug(sprintf('RuleTrigger HasNoBudget for journal #%d: count is %d, return false.', $journal->id, $count));

        return false;
    }
}
