<?php
/**
 * MigrateJournalNotesTest.php
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

namespace Tests\Unit\Console\Commands\Upgrade;


use FireflyConfig;
use FireflyIII\Models\Configuration;
use FireflyIII\Models\TransactionJournalMeta;
use Log;
use Tests\TestCase;

/**
 * Class MigrateJournalNotesTest
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class MigrateJournalNotesTest extends TestCase
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
     * @covers \FireflyIII\Console\Commands\Upgrade\MigrateJournalNotes
     */
    public function testHandle(): void
    {
        $false       = new Configuration;
        $false->data = false;
        FireflyConfig::shouldReceive('get')->withArgs(['480_migrate_notes', false])->andReturn($false);
        FireflyConfig::shouldReceive('set')->withArgs(['480_migrate_notes', true]);

        // assume all is well.
        $this->artisan('firefly-iii:migrate-notes')
             ->expectsOutput('No notes to migrate.')
             ->assertExitCode(0);
    }

    /**
     * @covers \FireflyIII\Console\Commands\Upgrade\MigrateJournalNotes
     */
    public function testHandleNote(): void
    {
        $false       = new Configuration;
        $false->data = false;
        FireflyConfig::shouldReceive('get')->withArgs(['480_migrate_notes', false])->andReturn($false);
        FireflyConfig::shouldReceive('set')->withArgs(['480_migrate_notes', true]);

        $journal = $this->getRandomWithdrawal();

        // delete any notes the journal may have already:
        $journal->notes()->forceDelete();

        $meta = TransactionJournalMeta::create(
            [
                'transaction_journal_id' => $journal->id,
                'name'                   => 'notes',
                'data'                   => json_encode('Some note.'),
                'hash'                   => 'Some hash',
            ]
        );
        // assume one is fixed.
        $this->artisan('firefly-iii:migrate-notes')
             ->expectsOutput('Migrated 1 note(s).')
             ->assertExitCode(0);

        $this->assertCount(0, TransactionJournalMeta
            ::where('name', 'notes')
            ->where('id', $meta->id)
            ->whereNull('deleted_at')
            ->get());

    }


}
