<?php
/**
 * RecurrenceStoreRequest.php
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

namespace FireflyIII\Api\V1\Requests;

use Carbon\Carbon;
use FireflyIII\Rules\BelongsUser;
use FireflyIII\Rules\IsBoolean;
use FireflyIII\Validation\RecurrenceValidation;
use FireflyIII\Validation\TransactionValidation;
use Illuminate\Validation\Validator;

/**
 * Class RecurrenceStoreRequest
 */
class RecurrenceStoreRequest extends Request
{
    use RecurrenceValidation, TransactionValidation;

    /**
     * Authorize logged in users.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Only allow authenticated users
        return auth()->check();
    }

    /**
     * Get all data from the request.
     *
     * @return array
     */
    public function getAll(): array
    {
        $active     = true;
        $applyRules = true;
        if (null !== $this->get('active')) {
            $active = $this->boolean('active');
        }
        if (null !== $this->get('apply_rules')) {
            $applyRules = $this->boolean('apply_rules');
        }
        $return = [
            'recurrence'   => [
                'type'         => $this->string('type'),
                'title'        => $this->string('title'),
                'description'  => $this->string('description'),
                'first_date'   => $this->date('first_date'),
                'repeat_until' => $this->date('repeat_until'),
                'repetitions'  => $this->integer('nr_of_repetitions'),
                'apply_rules'  => $applyRules,
                'active'       => $active,
            ],
            'transactions' => $this->getTransactionData(),
            'repetitions'  => $this->getRepetitionData(),
        ];

        return $return;
    }

    /**
     * The rules that the incoming request must be matched against.
     *
     * @return array
     */
    public function rules(): array
    {
        $today = Carbon::now()->addDay();

        return [
            'type'                                 => 'required|in:withdrawal,transfer,deposit',
            'title'                                => 'required|between:1,255|uniqueObjectForUser:recurrences,title',
            'description'                          => 'between:1,65000',
            'first_date'                           => 'required|date',
            'apply_rules'                          => [new IsBoolean],
            'active'                               => [new IsBoolean],
            'repeat_until'                         => sprintf('date|after:%s', $today->format('Y-m-d')),
            'nr_of_repetitions'                    => 'numeric|between:1,31',
            'repetitions.*.type'                   => 'required|in:daily,weekly,ndom,monthly,yearly',
            'repetitions.*.moment'                 => 'between:0,10',
            'repetitions.*.skip'                   => 'required|numeric|between:0,31',
            'repetitions.*.weekend'                => 'required|numeric|min:1|max:4',
            'transactions.*.description'           => 'required|between:1,255',
            'transactions.*.amount'                => 'required|numeric|more:0',
            'transactions.*.foreign_amount'        => 'numeric|more:0',
            'transactions.*.currency_id'           => 'numeric|exists:transaction_currencies,id',
            'transactions.*.currency_code'         => 'min:3|max:3|exists:transaction_currencies,code',
            'transactions.*.foreign_currency_id'   => 'numeric|exists:transaction_currencies,id',
            'transactions.*.foreign_currency_code' => 'min:3|max:3|exists:transaction_currencies,code',
            'transactions.*.source_id'             => ['numeric', 'nullable', new BelongsUser],
            'transactions.*.source_name'           => 'between:1,255|nullable',
            'transactions.*.destination_id'        => ['numeric', 'nullable', new BelongsUser],
            'transactions.*.destination_name'      => 'between:1,255|nullable',

            // new and updated fields:
            'transactions.*.budget_id'             => ['mustExist:budgets,id', new BelongsUser],
            'transactions.*.budget_name'           => ['between:1,255', 'nullable', new BelongsUser],
            'transactions.*.category_id'           => ['mustExist:categories,id', new BelongsUser],
            'transactions.*.category_name'         => 'between:1,255|nullable',
            'transactions.*.piggy_bank_id'         => ['numeric', 'mustExist:piggy_banks,id', new BelongsUser],
            'transactions.*.piggy_bank_name'       => ['between:1,255', 'nullable', new BelongsUser],
            'transactions.*.tags'                  => 'between:1,64000',


        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param Validator $validator
     *
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(
            function (Validator $validator) {
                $this->validateOneRecurrenceTransaction($validator);
                $this->validateOneRepetition($validator);
                $this->validateRecurrenceRepetition($validator);
                $this->validateRepetitionMoment($validator);
                $this->validateForeignCurrencyInformation($validator);
                $this->validateAccountInformation($validator);
            }
        );
    }

    /**
     * Returns the repetition data as it is found in the submitted data.
     *
     * @return array
     */
    private function getRepetitionData(): array
    {
        $return = [];
        // repetition data:
        /** @var array $repetitions */
        $repetitions = $this->get('repetitions');
        if (null === $repetitions) {
            return [];
        }
        /** @var array $repetition */
        foreach ($repetitions as $repetition) {
            $return[] = [
                'type'    => $repetition['type'],
                'moment'  => $repetition['moment'],
                'skip'    => (int)$repetition['skip'],
                'weekend' => (int)$repetition['weekend'],
            ];
        }

        return $return;
    }

    /**
     * Returns the transaction data as it is found in the submitted data. It's a complex method according to code
     * standards but it just has a lot of ??-statements because of the fields that may or may not exist.
     *
     * @return array
     */
    private function getTransactionData(): array
    {
        $return = [];
        // transaction data:
        /** @var array $transactions */
        $transactions = $this->get('transactions');
        if (null === $transactions) {
            return [];
        }
        /** @var array $transaction */
        foreach ($transactions as $transaction) {
            $return[] = [
                'amount'                => $transaction['amount'],
                'currency_id'           => isset($transaction['currency_id']) ? (int)$transaction['currency_id'] : null,
                'currency_code'         => $transaction['currency_code'] ?? null,
                'foreign_amount'        => $transaction['foreign_amount'] ?? null,
                'foreign_currency_id'   => isset($transaction['foreign_currency_id']) ? (int)$transaction['foreign_currency_id'] : null,
                'foreign_currency_code' => $transaction['foreign_currency_code'] ?? null,
                'source_id'             => isset($transaction['source_id']) ? (int)$transaction['source_id'] : null,
                'source_name'           => isset($transaction['source_name']) ? (string)$transaction['source_name'] : null,
                'destination_id'        => isset($transaction['destination_id']) ? (int)$transaction['destination_id'] : null,
                'destination_name'      => isset($transaction['destination_name']) ? (string)$transaction['destination_name'] : null,
                'description'           => $transaction['description'],
                'type'                  => $this->string('type'),

                // new and updated fields:
                'piggy_bank_id'         => isset($transaction['piggy_bank_id']) ? (int)$transaction['piggy_bank_id'] : null,
                'piggy_bank_name'       => $transaction['piggy_bank_name'] ?? null,
                'tags'                  => $transaction['tags'] ?? [],
                'budget_id'             => isset($transaction['budget_id']) ? (int)$transaction['budget_id'] : null,
                'budget_name'           => $transaction['budget_name'] ?? null,
                'category_id'           => isset($transaction['category_id']) ? (int)$transaction['category_id'] : null,
                'category_name'         => $transaction['category_name'] ?? null,
            ];
        }

        return $return;
    }
}
