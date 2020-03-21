<?php
/**
 * EnableCurrenciesTest.php
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


use FireflyIII\Models\BudgetLimit;
use FireflyIII\Models\TransactionCurrency;
use Log;
use Tests\TestCase;

/**
 * Class EnableCurrenciesTest
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class EnableCurrenciesTest extends TestCase
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
     * @covers \FireflyIII\Console\Commands\Correction\EnableCurrencies
     */
    public function testHandle(): void
    {
        // assume the current database is intact.
        $count = TransactionCurrency::where('enabled', 1)->count();

        $this->artisan('firefly-iii:enable-currencies')
             ->expectsOutput('All currencies are correctly enabled or disabled.')
             ->assertExitCode(0);


        $this->assertCount($count, TransactionCurrency::where('enabled', 1)->get());
    }

    /**
     * @covers \FireflyIII\Console\Commands\Correction\EnableCurrencies
     */
    public function testHandleDisabled(): void
    {
        // find a disabled currency, update a budget limit with it.
        $currency = TransactionCurrency::where('enabled', 0)->first();
        /** @var BudgetLimit $budgetLimit */
        $budgetLimit                          = BudgetLimit::inRandomOrder()->first();
        $budgetLimit->transaction_currency_id = $currency->id;
        $budgetLimit->save();

        // assume the current database is intact.
        $count = TransactionCurrency::where('enabled', 1)->count();
        $this->artisan('firefly-iii:enable-currencies')
             ->expectsOutput(sprintf('%d were (was) still disabled. This has been corrected.', 1))
             ->assertExitCode(0);

        // assume its been enabled.
        $this->assertCount($count + 1, TransactionCurrency::where('enabled', 1)->get());
    }

}
