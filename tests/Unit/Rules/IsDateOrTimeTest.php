<?php
/**
 * IsDateOrTimeTest.php
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


use FireflyIII\Rules\IsDateOrTime;
use Log;
use Tests\TestCase;

/**
 * Class IsDateOrTimeTest
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class IsDateOrTimeTest extends TestCase
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
     * @covers \FireflyIII\Rules\IsDateOrTime
     */
    public function testFalse(): void
    {
        $attribute = 'not-important';
        $values    = ['20xx-01-x','1234567890', '2xx0101', '', false];

        /** @var mixed $value */
        foreach ($values as $value) {
            $engine = new IsDateOrTime();
            $this->assertFalse($engine->passes($attribute, $value), sprintf('%s', var_export($value, true)));
        }
    }

    /**
     * @covers \FireflyIII\Rules\IsDateOrTime
     */
    public function testTrue(): void
    {
        $attribute = 'not-important';
        $values    = ['2019-01-01', '20190101', '2019-01-01 12:12:12', '12:12:12'];

        /** @var mixed $value */
        foreach ($values as $value) {
            $engine = new IsDateOrTime();
            $this->assertTrue($engine->passes($attribute, $value));
        }
    }


}
