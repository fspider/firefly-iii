<?php
/**
 * IsBooleanTest.php
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

namespace Tests\Unit\Rules;


use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Rules\IsBoolean;
use Log;
use Tests\TestCase;

/**
 * Class IsBooleanTest
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class IsBooleanTest extends TestCase
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
     * @covers \FireflyIII\Rules\IsBoolean
     */
    public function testFalse(): void
    {
        $attribute = 'not-important';

        $false = ['not', 2, -1, []];

        /** @var mixed $value */
        foreach ($false as $value) {

            $engine = new IsBoolean();
                $this->assertFalse($engine->passes($attribute, $value));
        }
    }

    /**
     * @covers \FireflyIII\Rules\IsBoolean
     */
    public function testTrue(): void
    {
        $attribute = 'not-important';

        $true = [true, false, 0, 1, '0', '1', 'true', 'false', 'yes', 'no', 'on', 'off'];

        /** @var mixed $value */
        foreach ($true as $value) {

            $engine = new IsBoolean();
                $this->assertTrue($engine->passes($attribute, $value));
        }
    }

}
