<?php
/**
 * AvailableBudgetRepository.php
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

namespace FireflyIII\Repositories\Budget;

use Carbon\Carbon;
use Exception;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Models\AvailableBudget;
use FireflyIII\Models\TransactionCurrency;
use FireflyIII\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Log;

/**
 *
 * Class AvailableBudgetRepository
 */
class AvailableBudgetRepository implements AvailableBudgetRepositoryInterface
{
    /** @var User */
    private $user;

    /**
     * Constructor.
     */
    public function __construct()
    {
        if ('testing' === config('app.env')) {
            Log::warning(sprintf('%s should not be instantiated in the TEST environment!', get_class($this)));
            die(get_class($this));
        }
    }

    /**
     * @param AvailableBudget $availableBudget
     */
    public function destroyAvailableBudget(AvailableBudget $availableBudget): void
    {
        try {
            $availableBudget->delete();
        } catch (Exception $e) {
            Log::error(sprintf('Could not delete available budget: %s', $e->getMessage()));
        }
    }

    /**
     * Find existing AB.
     *
     * @param TransactionCurrency $currency
     * @param Carbon              $start
     * @param Carbon              $end
     *
     * @return AvailableBudget|null
     */
    public function find(TransactionCurrency $currency, Carbon $start, Carbon $end): ?AvailableBudget
    {
        return $this->user->availableBudgets()
                          ->where('transaction_currency_id', $currency->id)
                          ->where('start_date', $start->format('Y-m-d 00:00:00'))
                          ->where('end_date', $end->format('Y-m-d 00:00:00'))
                          ->first();

    }

    /**
     * Return a list of all available budgets (in all currencies) (for the selected period).
     *
     * @param Carbon|null $start
     * @param Carbon|null $end
     *
     * @return Collection
     */
    public function get(?Carbon $start = null, ?Carbon $end = null): Collection
    {
        $query = $this->user->availableBudgets()->with(['transactionCurrency']);
        if (null !== $start && null !== $end) {
            $query->where(
                static function (Builder $q1) use ($start, $end) {
                    $q1->where('start_date', '=', $start->format('Y-m-d 00:00:00'));
                    $q1->where('end_date', '=', $end->format('Y-m-d 00:00:00'));
                }
            );
        }

        return $query->get(['available_budgets.*']);
    }

    /**
     * @param TransactionCurrency $currency
     * @param Carbon              $start
     * @param Carbon              $end
     *
     * @return string
     */
    public function getAvailableBudget(TransactionCurrency $currency, Carbon $start, Carbon $end): string
    {
        $amount          = '0';
        $availableBudget = $this->user->availableBudgets()
                                      ->where('transaction_currency_id', $currency->id)
                                      ->where('start_date', $start->format('Y-m-d 00:00:00'))
                                      ->where('end_date', $end->format('Y-m-d 00:00:00'))->first();
        if (null !== $availableBudget) {
            $amount = (string)$availableBudget->amount;
        }

        return $amount;
    }

    /**
     * @param Carbon $start
     * @param Carbon $end
     *
     * @return array
     */
    public function getAvailableBudgetWithCurrency(Carbon $start, Carbon $end): array
    {
        $return           = [];
        $availableBudgets = $this->user->availableBudgets()
                                       ->where('start_date', $start->format('Y-m-d'))
                                       ->where('end_date', $end->format('Y-m-d'))->get();
        /** @var AvailableBudget $availableBudget */
        foreach ($availableBudgets as $availableBudget) {
            $return[$availableBudget->transaction_currency_id] = $availableBudget->amount;
        }

        return $return;
    }

    /**
     * Returns all available budget objects.
     *
     * @param TransactionCurrency $currency
     *
     * @return Collection
     */
    public function getAvailableBudgetsByCurrency(TransactionCurrency $currency): Collection
    {
        return $this->user->availableBudgets()->where('transaction_currency_id', $currency->id)->get();
    }

    /**
     * Returns all available budget objects.
     *
     * @param Carbon|null $start
     * @param Carbon|null $end
     *
     * @return Collection
     *
     */
    public function getAvailableBudgetsByDate(?Carbon $start, ?Carbon $end): Collection
    {
        $query = $this->user->availableBudgets();

        if (null !== $start) {
            $query->where('start_date', '>=', $start->format('Y-m-d H:i:s'));
        }
        if (null !== $end) {
            $query->where('end_date', '<=', $end->format('Y-m-d H:i:s'));
        }

        return $query->get();
    }

    /**
     * @param TransactionCurrency $currency
     * @param Carbon              $start
     * @param Carbon              $end
     * @param string              $amount
     *
     * @return AvailableBudget
     * @deprecated
     */
    public function setAvailableBudget(TransactionCurrency $currency, Carbon $start, Carbon $end, string $amount): AvailableBudget
    {
        $availableBudget = $this->user->availableBudgets()
                                      ->where('transaction_currency_id', $currency->id)
                                      ->where('start_date', $start->format('Y-m-d 00:00:00'))
                                      ->where('end_date', $end->format('Y-m-d 00:00:00'))->first();
        if (null === $availableBudget) {
            $availableBudget = new AvailableBudget;
            $availableBudget->user()->associate($this->user);
            $availableBudget->transactionCurrency()->associate($currency);
            $availableBudget->start_date = $start->format('Y-m-d 00:00:00');
            $availableBudget->end_date   = $end->format('Y-m-d 00:00:00');
        }
        $availableBudget->amount = $amount;
        $availableBudget->save();

        return $availableBudget;
    }

    /**
     * @param User $user
     */
    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    /**
     * @param array $data
     *
     * @return AvailableBudget|null
     */
    public function store(array $data): ?AvailableBudget
    {
        return AvailableBudget::create(
            [
                'user_id'                 => $this->user->id,
                'transaction_currency_id' => $data['currency']->id,
                'amount'                  => $data['amount'],
                'start_date'              => $data['start'],
                'end_date'                => $data['end'],

            ]
        );
    }

    /**
     * @param AvailableBudget $availableBudget
     * @param array           $data
     *
     * @return AvailableBudget
     */
    public function update(AvailableBudget $availableBudget, array $data): AvailableBudget
    {
        if (isset($data['amount'])) {
            $availableBudget->amount = $data['amount'];
        }
        $availableBudget->save();

        return $availableBudget;
    }

    /**
     * @param AvailableBudget $availableBudget
     * @param array           $data
     *
     * @return AvailableBudget
     * @throws FireflyException
     */
    public function updateAvailableBudget(AvailableBudget $availableBudget, array $data): AvailableBudget
    {
        $existing = $this->user->availableBudgets()
                               ->where('transaction_currency_id', $data['currency_id'])
                               ->where('start_date', $data['start']->format('Y-m-d 00:00:00'))
                               ->where('end_date', $data['end']->format('Y-m-d 00:00:00'))
                               ->where('id', '!=', $availableBudget->id)
                               ->first();

        if (null !== $existing) {
            throw new FireflyException(sprintf('An entry already exists for these parameters: available budget object with ID #%d', $existing->id));
        }
        $availableBudget->transaction_currency_id = $data['currency_id'];
        $availableBudget->start_date              = $data['start'];
        $availableBudget->end_date                = $data['end'];
        $availableBudget->amount                  = $data['amount'];
        $availableBudget->save();

        return $availableBudget;

    }

    /**
     * Delete all available budgets.
     */
    public function destroyAll(): void
    {
        $this->user->availableBudgets()->delete();
    }
}