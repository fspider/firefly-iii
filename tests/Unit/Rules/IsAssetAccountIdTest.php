<?php
/**
 * IsAssetAccountIdTest.php
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
use FireflyIII\Rules\IsAssetAccountId;
use Log;
use Tests\TestCase;

/**
 * Class IsAssetAccountIdTest
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class IsAssetAccountIdTest extends TestCase
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
     * @covers \FireflyIII\Rules\IsAssetAccountId
     */
    public function testNotAsset(): void
    {
        $attribute = 'not-used';
        $expense   = $this->getRandomExpense();
        $value     = $expense->id;

        $engine = new IsAssetAccountId();
            $this->assertFalse($engine->passes($attribute, $value));
    }


    /**
     * @covers \FireflyIII\Rules\IsAssetAccountId
     */
    public function testAsset(): void
    {
        $attribute = 'not-used';
        $asset   = $this->getRandomAsset();
        $value     = $asset->id;

        $engine = new IsAssetAccountId();
            $this->assertTrue($engine->passes($attribute, $value));
    }

    /**
     * @covers \FireflyIII\Rules\IsAssetAccountId
     */
    public function testNull(): void
    {
        $attribute = 'not-used';
        $value     = '-1';

        $engine = new IsAssetAccountId();
            $this->assertFalse($engine->passes($attribute, $value));
    }

}
