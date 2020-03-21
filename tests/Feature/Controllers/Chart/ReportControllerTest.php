<?php
/**
 * ReportControllerTest.php
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

namespace Tests\Feature\Controllers\Chart;

use Carbon\Carbon;
use FireflyIII\Generator\Chart\Basic\GeneratorInterface;
use FireflyIII\Helpers\Fiscal\FiscalHelperInterface;
use FireflyIII\Helpers\Report\NetWorthInterface;
use FireflyIII\Repositories\Account\AccountRepositoryInterface;
use FireflyIII\Repositories\Account\AccountTaskerInterface;
use FireflyIII\Repositories\Currency\CurrencyRepositoryInterface;
use Log;
use Mockery;
use Preferences;
use Steam;
use Tests\TestCase;

/**
 * Class ReportControllerTest
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ReportControllerTest extends TestCase
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
     * @covers \FireflyIII\Http\Controllers\Chart\ReportController
     */
    public function testNetWorth(): void
    {
        $generator     = $this->mock(GeneratorInterface::class);
        $accountRepos  = $this->mock(AccountRepositoryInterface::class);
        $currencyRepos = $this->mock(CurrencyRepositoryInterface::class);
        $fiscalHelper  = $this->mock(FiscalHelperInterface::class);
        $netWorth      = $this->mock(NetWorthInterface::class);
        $date          = new Carbon;
        $fiscalHelper->shouldReceive('endOfFiscalYear')->atLeast()->once()->andReturn($date);
        $fiscalHelper->shouldReceive('startOfFiscalYear')->atLeast()->once()->andReturn($date);


        $this->mockDefaultSession();
        Preferences::shouldReceive('lastActivity')->atLeast()->once()->andReturn('md512345');

        $netWorth->shouldReceive('getNetWorthByCurrency')->andReturn(
            [
                [
                    'currency' => $this->getEuro(),
                    'balance'  => '123',
                ],
            ]
        );
        $netWorth->shouldReceive('setUser')->atLeast()->once();

        // mock calls:
        $accountRepos->shouldReceive('setUser');

        $accountRepos->shouldReceive('getMetaValue')->times(2)
                     ->withArgs([Mockery::any(), 'include_net_worth'])->andReturn('1', '0');
        $accountRepos->shouldReceive('getMetaValue')
                     ->withArgs([Mockery::any(), 'currency_id'])->andReturn(1);
        $accountRepos->shouldReceive('getMetaValue')
                     ->withArgs([Mockery::any(), 'accountRole'])->andReturn('ccAsset');


        Steam::shouldReceive('balancesByAccounts')->andReturn(['5', '10']);
        $generator->shouldReceive('multiSet')->andReturn([]);

        $this->be($this->user());
        $response = $this->get(route('chart.report.net-worth', ['1,2', '20120101', '20120131']));
        $response->assertStatus(200);
    }

    /**
     * @covers \FireflyIII\Http\Controllers\Chart\ReportController
     */
    public function testOperations(): void
    {
        $generator     = $this->mock(GeneratorInterface::class);
        $tasker        = $this->mock(AccountTaskerInterface::class);
        $currencyRepos = $this->mock(CurrencyRepositoryInterface::class);
        $fiscalHelper  = $this->mock(FiscalHelperInterface::class);
        $date          = new Carbon;

        $this->mockDefaultSession();
        Preferences::shouldReceive('lastActivity')->atLeast()->once()->andReturn('md512345');

        $fiscalHelper->shouldReceive('endOfFiscalYear')->atLeast()->once()->andReturn($date);
        $fiscalHelper->shouldReceive('startOfFiscalYear')->atLeast()->once()->andReturn($date);
        $income  = [
            'accounts' => [
                1 => ['sum' => '100']]];
        $expense = [
            'accounts' => [
                2 => ['sum' => '-100']]];
        $generator->shouldReceive('multiSet')->andReturn([]);

        $this->be($this->user());
        $response = $this->get(route('chart.report.operations', [1, '20120101', '20120131']));
        $response->assertStatus(200);
    }
}
