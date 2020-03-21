<?php
/**
 * MetadataParserTest.php
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

namespace Tests\Unit\Support\FinTS;

use Fhp\Model\StatementOfAccount\Transaction as FinTSTransaction;
use FireflyIII\Support\FinTS\MetadataParser;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 *
 * Class MetadataParserTest
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class MetadataParserTest extends TestCase
{

    /** @var MetadataParser */
    private $metadataParser;

    /**
     *
     */
    public function setUp(): void
    {
        parent::setUp();
        Log::info(sprintf('Now in %s.', get_class($this)));
        $this->metadataParser = new MetadataParser();
    }

    /**
     * @covers \FireflyIII\Support\FinTS\MetadataParser
     */
    public function testDescriptionIsCorrectlyExtractedFromBeginning(): void
    {
        $transaction = $this->createTransactionWithDescription1('SVWZ+DescriptionABWA+xxx');
        $this->assertEquals('Description', $this->metadataParser->getDescription($transaction));
    }

    /**
     * @covers \FireflyIII\Support\FinTS\MetadataParser
     */
    public function testDescriptionIsCorrectlyExtractedFromEnd(): void
    {
        $transaction = $this->createTransactionWithDescription1('EREF+AbcCRED+DE123SVWZ+Description');
        $this->assertEquals('Description', $this->metadataParser->getDescription($transaction));
    }

    /**
     * @covers \FireflyIII\Support\FinTS\MetadataParser
     */
    public function testDescriptionIsCorrectlyExtractedFromMiddle(): void
    {
        $transaction = $this->createTransactionWithDescription1('EREF+AbcCRED+DE123SVWZ+DescriptionABWA+Ghi');
        $this->assertEquals('Description', $this->metadataParser->getDescription($transaction));
    }

    /**
     * @param string $description1
     *
     * @return FinTSTransaction
     */
    private function createTransactionWithDescription1(string $description1): FinTSTransaction
    {
        $transaction = $this->mock(FinTSTransaction::class);
        $transaction->shouldReceive('getDescription1')->atLeast()->once()->andReturn($description1);

        return $transaction;
    }
}
