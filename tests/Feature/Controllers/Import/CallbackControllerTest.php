<?php
/**
 * CallbackControllerTest.php
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

namespace Tests\Feature\Controllers\Import;


use FireflyIII\Models\ImportJob;
use FireflyIII\Repositories\ImportJob\ImportJobRepositoryInterface;
use Log;
use Mockery;
use Tests\TestCase;

/**
 *
 * Class CallbackControllerTest
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class CallbackControllerTest extends TestCase
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
     * @covers \FireflyIII\Http\Controllers\Import\CallbackController
     */
    public function testYnabBasic(): void
    {
        $repository = $this->mock(ImportJobRepositoryInterface::class);
        $importJob = $this->getRandomImportJob();
        // config for job:
        $config    = [];
        $newConfig = ['auth_code' => 'abc'];

        $this->mockDefaultSession();

        // mock calls.
        $repository->shouldReceive('findByKey')->andReturn($importJob)->once();
        $repository->shouldReceive('getConfiguration')->andReturn($config)->once();
        $repository->shouldReceive('setConfiguration')->once()->withArgs([Mockery::any(), $newConfig]);

        $repository->shouldReceive('setStatus')->once()->withArgs([Mockery::any(), 'ready_to_run']);
        $repository->shouldReceive('setStage')->once()->withArgs([Mockery::any(), 'get_access_token']);


        $this->be($this->user());
        $response = $this->get(route('import.callback.ynab') . '?code=abc&state=def');
        $response->assertStatus(302);
    }

    /**
     * @covers \FireflyIII\Http\Controllers\Import\CallbackController
     */
    public function testYnabBasicBadJob(): void
    {
        $repository = $this->mock(ImportJobRepositoryInterface::class);

        // mock calls.
        $repository->shouldReceive('findByKey')->andReturnNull()->once();
        $this->mockDefaultSession();

        $this->be($this->user());
        $response = $this->get(route('import.callback.ynab') . '?code=abc&state=def');
        $response->assertStatus(200);
        $response->assertSee('You Need A Budget did not reply with the correct state identifier. Firefly III cannot continue.');
    }

    /**
     * @covers \FireflyIII\Http\Controllers\Import\CallbackController
     */
    public function testYnabBasicNoCode(): void
    {
        $this->mock(ImportJobRepositoryInterface::class);

        $this->mockDefaultSession();

        // mock calls.

        $this->be($this->user());
        $response = $this->get(route('import.callback.ynab') . '?code=&state=def');
        $response->assertStatus(200);
        $response->assertSee('You Need A Budget did not reply with a valid authorization code. Firefly III cannot continue.');
    }
}
