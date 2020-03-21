<?php
/**
 * BudgetDestroyService.php
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

namespace FireflyIII\Services\Internal\Destroy;

use DB;
use Exception;
use FireflyIII\Models\Budget;
use Log;

/**
 * Class BudgetDestroyService
 * @codeCoverageIgnore
 */
class BudgetDestroyService
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        if ('testing' === config('app.env')) {
            Log::warning(sprintf('%s should not be instantiated in the TEST environment!', get_class($this)));
        }
    }

    /**
     * @param Budget $budget
     */
    public function destroy(Budget $budget): void
    {
        try {
            $budget->delete();
        } catch (Exception $e) { // @codeCoverageIgnore
            Log::error(sprintf('Could not delete budget: %s', $e->getMessage())); // @codeCoverageIgnore
        }

        // also delete all relations between categories and transaction journals:
        DB::table('budget_transaction_journal')->where('budget_id', (int)$budget->id)->delete();

        // also delete all relations between categories and transactions:
        DB::table('budget_transaction')->where('budget_id', (int)$budget->id)->delete();

        // also delete all budget limits
        $budget->budgetlimits()->delete();
    }
}
