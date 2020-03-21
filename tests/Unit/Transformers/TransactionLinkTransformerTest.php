<?php
/**
 * TransactionLinkTransformerTest.php
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

namespace Tests\Unit\Transformers;


use FireflyIII\Models\TransactionJournalLink;
use FireflyIII\Repositories\Journal\JournalRepositoryInterface;
use FireflyIII\Transformers\TransactionLinkTransformer;
use Symfony\Component\HttpFoundation\ParameterBag;
use Tests\TestCase;

/**
 * Class TransactionLinkTransformerTest
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class TransactionLinkTransformerTest extends TestCase
{
    /**
     * Test basic tag transformer
     *
     * @covers \FireflyIII\Transformers\TransactionLinkTransformer
     */
    public function testBasic(): void
    {
        $repository = $this->mock(JournalRepositoryInterface::class);

        $repository->shouldReceive('getLinkNoteText')->atLeast()->once()->andReturn('abc');

        /** @var TransactionJournalLink $link */
        $link = TransactionJournalLink::first();

        $transformer = app(TransactionLinkTransformer::class);
        $transformer->setParameters(new ParameterBag);

        $result = $transformer->transform($link);

        $this->assertEquals($link->source_id, $result['inward_id']);
        $this->assertEquals('abc', $result['notes']);

    }

}
