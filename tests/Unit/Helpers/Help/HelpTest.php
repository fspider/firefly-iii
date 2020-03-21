<?php
/**
 * HelpTest.php
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

namespace Tests\Unit\Helpers\Help;


use FireflyIII\Helpers\Help\Help;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Log;
use Tests\TestCase;

/**
 *
 * Class HelpTest
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class HelpTest extends TestCase
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
     * @covers \FireflyIII\Helpers\Help\Help
     */
    public function testGetFromGitHub(): void
    {
        $headers  = ['Content-Type' => 'application/json'];
        $response = new Response(200, $headers, 'Some help text.');
        $mock     = new MockHandler([$response]);

        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        //client instance is bound to the mock here.
        $this->app->instance(Client::class, $client);


        // now let's see what happens:
        $help   = new Help;
        $result = $help->getFromGitHub('test-route', 'en_US');
        $this->assertEquals('<p>Some help text.</p>' . "\n", $result);
    }

    /**
     * @covers \FireflyIII\Helpers\Help\Help
     */
    public function testGetFromGitHubError(): void
    {
        $headers  = ['Content-Type' => 'application/json'];
        $response = new Response(500, $headers, 'Big bad error.');
        $mock     = new MockHandler([$response]);

        $handler = HandlerStack::create($mock);
        $client  = new Client(['handler' => $handler]);

        //client instance is bound to the mock here.
        $this->app->instance(Client::class, $client);

        Log::warning('The following error is part of a test.');
        // now let's see what happens:
        $help   = new Help;
        $result = $help->getFromGitHub('test-route', 'en_US');
        $this->assertEquals('', $result);
    }


}
