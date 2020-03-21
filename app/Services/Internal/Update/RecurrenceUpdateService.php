<?php
/**
 * RecurrenceUpdateService.php
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

namespace FireflyIII\Services\Internal\Update;

use Exception;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Models\Note;
use FireflyIII\Models\Recurrence;
use FireflyIII\Models\TransactionJournalLink;
use FireflyIII\Services\Internal\Support\RecurringTransactionTrait;
use FireflyIII\Services\Internal\Support\TransactionTypeTrait;
use FireflyIII\User;
use Log;

/**
 * Class RecurrenceUpdateService
 *
 * @codeCoverageIgnore
 */
class RecurrenceUpdateService
{
    use TransactionTypeTrait, RecurringTransactionTrait;

    /** @var User */
    private $user;

    /**
     * Updates a recurrence.
     *
     * TODO if the user updates the type, accounts must be validated (again).
     *
     * @param Recurrence $recurrence
     * @param array      $data
     *
     * @return Recurrence
     * @throws FireflyException
     */
    public function update(Recurrence $recurrence, array $data): Recurrence
    {
        $this->user      = $recurrence->user;
        $transactionType = $recurrence->transactionType;
        if (isset($data['recurrence']['type'])) {
            $transactionType = $this->findTransactionType(ucfirst($data['recurrence']['type']));
        }
        // update basic fields first:
        $recurrence->transaction_type_id = $transactionType->id;
        $recurrence->title               = $data['recurrence']['title'] ?? $recurrence->title;
        $recurrence->description         = $data['recurrence']['description'] ?? $recurrence->description;
        $recurrence->first_date          = $data['recurrence']['first_date'] ?? $recurrence->first_date;
        $recurrence->repeat_until        = $data['recurrence']['repeat_until'] ?? $recurrence->repeat_until;
        $recurrence->repetitions         = $data['recurrence']['nr_of_repetitions'] ?? $recurrence->repetitions;
        $recurrence->apply_rules         = $data['recurrence']['apply_rules'] ?? $recurrence->apply_rules;
        $recurrence->active              = $data['recurrence']['active'] ?? $recurrence->active;

        // if nr_of_repetitions is set, then drop the "repeat_until" field.
        if (0 !== $recurrence->repetitions) {
            $recurrence->repeat_until = null;
        }

        if (isset($data['recurrence']['repetition_end'])) {
            if (in_array($data['recurrence']['repetition_end'], ['forever', 'until_date'])) {
                $recurrence->repetitions = 0;
            }
            if (in_array($data['recurrence']['repetition_end'], ['forever', 'times'])) {
                $recurrence->repeat_until = null;
            }
        }
        $recurrence->save();

        // update all meta data:
        //$this->updateMetaData($recurrence, $data);

        if (isset($data['recurrence']['notes']) && null !== $data['recurrence']['notes']) {
            $this->setNoteText($recurrence, $data['recurrence']['notes']);
        }

        // update all repetitions
        if (null !== $data['repetitions']) {
            $this->deleteRepetitions($recurrence);
            $this->createRepetitions($recurrence, $data['repetitions'] ?? []);
        }

        // update all transactions (and associated meta-data);
        if (null !== $data['transactions']) {
            $this->deleteTransactions($recurrence);
            $this->createTransactions($recurrence, $data['transactions'] ?? []);
        }

        return $recurrence;
    }

    /**
     * @param Recurrence $recurrence
     * @param string     $text
     */
    private function setNoteText(Recurrence $recurrence, string $text): void
    {
        $dbNote = $recurrence->notes()->first();
        if ('' !== $text) {
            if (null === $dbNote) {
                $dbNote = new Note();
                $dbNote->noteable()->associate($recurrence);
            }
            $dbNote->text = trim($text);
            $dbNote->save();

            return;
        }
        if (null !== $dbNote && '' === $text) {
            try {
                $dbNote->delete();
            } catch (Exception $e) {
                Log::debug(sprintf('Could not delete note: %s', $e->getMessage()));
            }
        }

    }
}
