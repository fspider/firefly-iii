<?php
/**
 * PrependNotesTest.php
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

namespace Tests\Unit\TransactionRules\Actions;

use FireflyIII\Models\Note;
use FireflyIII\Models\RuleAction;
use FireflyIII\Models\TransactionJournal;
use FireflyIII\TransactionRules\Actions\PrependNotes;
use Tests\TestCase;

/**
 * Class PrependNotesTest
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class PrependNotesTest extends TestCase
{
    /**
     * @covers \FireflyIII\TransactionRules\Actions\PrependNotes
     */
    public function testAct(): void
    {
        // give journal some notes.
        $journal   = $this->getRandomWithdrawal();
        $note      = $journal->notes()->first();
        $start     = 'Default note text';
        $toPrepend = 'This is prepended';
        if (null === $note) {
            $note = new Note();
            $note->noteable()->associate($journal);
        }
        $note->text = $start;
        $note->save();

        // fire the action:
        $ruleAction               = new RuleAction;
        $ruleAction->action_value = $toPrepend;
        $action                   = new PrependNotes($ruleAction);
        $result                   = $action->act($journal);
        $this->assertTrue($result);

        $newNote = $journal->notes()->first();
        $this->assertEquals($toPrepend . $start, $newNote->text);
    }

    /**
     * @covers \FireflyIII\TransactionRules\Actions\PrependNotes
     */
    public function testActNewNote(): void
    {
        // give journal some notes.
        $journal = $this->getRandomWithdrawal();
        $note    = $journal->notes()->first();
        if (null !== $note) {
            $note->forceDelete();
        }
        $toPrepend = 'This is appended';

        // fire the action:
        $ruleAction               = new RuleAction;
        $ruleAction->action_value = $toPrepend;
        $action                   = new PrependNotes($ruleAction);
        $result                   = $action->act($journal);
        $this->assertTrue($result);

        $newNote = $journal->notes()->first();
        $this->assertEquals($toPrepend, $newNote->text);
    }
}
