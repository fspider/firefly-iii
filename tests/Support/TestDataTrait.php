<?php
/**
 * TestDataTrait.php
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

namespace Tests\Support;

use Carbon\Carbon;
use Exception;
use FireflyIII\Models\TransactionCurrency;

/**
 * Trait TestDataTrait
 *
 * @package Tests\Support
 */
trait TestDataTrait
{
    /**
     * Method that returns default data for when the category OperationsRepos
     * "listExpenses" method is called.
     *
     * @return array
     */
    protected function categoryListExpenses(): array
    {
        $eur    = TransactionCurrency::where('code', 'EUR')->first();
        $usd    = TransactionCurrency::where('code', 'USD')->first();
        $cat1   = $this->user()->categories()->inRandomOrder()->first();
        $cat2   = $this->user()->categories()->inRandomOrder()->where('id', '!=', $cat1->id)->first();
        $data   = [];
        $amount = 400;
        $date   = null;
        try {
            $amount = random_int(100, 2500);
            $date   = new Carbon;
        } catch (Exception $e) {
            $e->getMessage();
        }
        $amount = bcmul((string)round($amount / 100, 2), '-1');

        foreach ([$eur, $usd] as $currency) {
            $data[$currency->id] = [
                'currency_id'             => $currency->id,
                'currency_name'           => $currency->name,
                'currency_symbol'         => $currency->symbol,
                'currency_code'           => $currency->code,
                'currency_decimal_places' => $currency->decimal_places,
                'categories'              => [],
            ];
            foreach ([$cat1, $cat2] as $category) {
                $data[$currency->id]['categories'][$category->id] = [
                    'id'                   => $category->id,
                    'name'                 => $category->name,
                    'transaction_journals' => [],
                ];
                // add two random amounts:
                for ($i = 0; $i < 2; $i++) {
                    $data[$currency->id]['categories'][$category->id]['transaction_journals'][$i] = [
                        'amount' => $amount,
                        'date'   => $date,
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Method that returns default data for when the tag OperationsRepos
     * "listExpenses" method is called.
     *
     * @return array
     */
    protected function tagListExpenses(): array
    {
        $eur    = TransactionCurrency::where('code', 'EUR')->first();
        $usd    = TransactionCurrency::where('code', 'USD')->first();
        $tag1   = $this->user()->tags()->inRandomOrder()->first();
        $tag2   = $this->user()->tags()->inRandomOrder()->where('id', '!=', $tag1->id)->first();
        $data   = [];
        $amount = 400;
        $date   = null;
        try {
            $amount = random_int(100, 2500);
            $date   = new Carbon;
        } catch (Exception $e) {
            $e->getMessage();
        }
        $amount = bcmul((string)round($amount / 100, 2), '-1');

        foreach ([$eur, $usd] as $currency) {
            $data[$currency->id] = [
                'currency_id'             => $currency->id,
                'currency_name'           => $currency->name,
                'currency_symbol'         => $currency->symbol,
                'currency_code'           => $currency->code,
                'currency_decimal_places' => $currency->decimal_places,
                'categories'              => [],
            ];
            foreach ([$tag1, $tag2] as $tag) {
                $data[$currency->id]['tags'][$tag->id] = [
                    'id'                   => $tag->id,
                    'name'                 => $tag->tag,
                    'transaction_journals' => [],
                ];
                // add two random amounts:
                for ($i = 0; $i < 2; $i++) {
                    $data[$currency->id]['categories'][$tag->id]['transaction_journals'][$i] = [
                        'amount' => $amount,
                        'date'   => $date,
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Method that returns default data for when the tag OperationsRepos
     * "listIncome" method is called.
     *
     * @return array
     */
    protected function tagListIncome(): array
    {
        $eur    = TransactionCurrency::where('code', 'EUR')->first();
        $usd    = TransactionCurrency::where('code', 'USD')->first();
        $tag1   = $this->user()->tags()->inRandomOrder()->first();
        $tag2   = $this->user()->tags()->inRandomOrder()->where('id', '!=', $tag1->id)->first();
        $data   = [];
        $amount = 400;
        $date   = null;
        try {
            $amount = random_int(100, 2500);
            $date   = new Carbon;
        } catch (Exception $e) {
            $e->getMessage();
        }
        $amount = (string)round($amount / 100, 2);

        foreach ([$eur, $usd] as $currency) {
            $data[$currency->id] = [
                'currency_id'             => $currency->id,
                'currency_name'           => $currency->name,
                'currency_symbol'         => $currency->symbol,
                'currency_code'           => $currency->code,
                'currency_decimal_places' => $currency->decimal_places,
                'categories'              => [],
            ];
            foreach ([$tag1, $tag2] as $tag) {
                $data[$currency->id]['tags'][$tag->id] = [
                    'id'                   => $tag->id,
                    'name'                 => $tag->tag,
                    'transaction_journals' => [],
                ];
                // add two random amounts:
                for ($i = 0; $i < 2; $i++) {
                    $data[$currency->id]['categories'][$tag->id]['transaction_journals'][$i] = [
                        'amount' => $amount,
                        'date'   => $date,
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Method that returns default data for when the category OperationsRepos
     * "listExpenses" method is called.
     *
     * @return array
     */
    protected function budgetListExpenses(): array
    {
        $eur    = TransactionCurrency::where('code', 'EUR')->first();
        $usd    = TransactionCurrency::where('code', 'USD')->first();
        $bud1   = $this->user()->budgets()->inRandomOrder()->first();
        $bud2   = $this->user()->budgets()->inRandomOrder()->where('id', '!=', $bud1->id)->first();
        $data   = [];
        $amount = 400;
        $date   = null;
        try {
            $amount = random_int(100, 2500);
            $date   = new Carbon;
        } catch (Exception $e) {
            $e->getMessage();
        }
        $amount = bcmul((string)round($amount / 100, 2), '-1');

        foreach ([$eur, $usd] as $currency) {
            $data[$currency->id] = [
                'currency_id'             => $currency->id,
                'currency_name'           => $currency->name,
                'currency_symbol'         => $currency->symbol,
                'currency_code'           => $currency->code,
                'currency_decimal_places' => $currency->decimal_places,
                'categories'              => [],
            ];
            foreach ([$bud1, $bud2] as $budget) {
                $data[$currency->id]['budgets'][$budget->id] = [
                    'id'                   => $budget->id,
                    'name'                 => $budget->name,
                    'transaction_journals' => [],
                ];
                // add two random amounts:
                for ($i = 0; $i < 2; $i++) {
                    $data[$currency->id]['budgets'][$budget->id]['transaction_journals'][$i] = [
                        'amount' => $amount,
                        'date'   => $date,
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Method that returns default data for when the category OperationsRepos
     * "listExpenses" method is called.
     *
     * @return array
     */
    protected function categoryListIncome(): array
    {
        $eur    = TransactionCurrency::where('code', 'EUR')->first();
        $usd    = TransactionCurrency::where('code', 'USD')->first();
        $cat1   = $this->user()->categories()->inRandomOrder()->first();
        $cat2   = $this->user()->categories()->inRandomOrder()->where('id', '!=', $cat1->id)->first();
        $data   = [];
        $amount = 400;
        $date   = null;
        try {
            $amount = random_int(100, 2500);
            $date   = new Carbon;
        } catch (Exception $e) {
            $e->getMessage();
        }
        $amount = (string)round($amount / 100, 2);

        foreach ([$eur, $usd] as $currency) {
            $data[$currency->id] = [
                'currency_id'             => $currency->id,
                'currency_name'           => $currency->name,
                'currency_symbol'         => $currency->symbol,
                'currency_code'           => $currency->code,
                'currency_decimal_places' => $currency->decimal_places,
                'categories'              => [],
            ];
            foreach ([$cat1, $cat2] as $category) {
                $data[$currency->id]['categories'][$category->id] = [
                    'id'                   => $category->id,
                    'name'                 => $category->name,
                    'transaction_journals' => [],
                ];
                // add two random amounts:
                for ($i = 0; $i < 2; $i++) {
                    $data[$currency->id]['categories'][$category->id]['transaction_journals'][$i] = [
                        'amount' => $amount,
                        'date'   => $date,
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Method that returns default data for when the category OperationsController
     * "sumExpenses" method is called.
     *
     * Also applies to NoCategoryRepos::sumExpenses.
     *
     * @return array
     */
    protected function categorySumExpenses(): array
    {
        $eur    = TransactionCurrency::where('code', 'EUR')->first();
        $usd    = TransactionCurrency::where('code', 'USD')->first();
        $data   = [];
        $amount = 400;
        try {
            $amount = random_int(100, 2500);
        } catch (Exception $e) {
            $e->getMessage();
        }
        $amount = bcmul((string)round($amount / 100, 2), '-1');

        foreach ([$eur, $usd] as $currency) {
            $data[$currency->id] = [
                'currency_id'             => $currency->id,
                'currency_name'           => $currency->name,
                'currency_symbol'         => $currency->symbol,
                'currency_code'           => $currency->code,
                'currency_decimal_places' => $currency->decimal_places,
                'sum'                     => $amount,
            ];
        }

        return $data;
    }

    /**
     * Method that returns default data for when the budget OperationsController
     * "sumExpenses" method is called.
     *
     * Also works for NoBudgetRepos::sumExpenses
     *
     * @return array
     */
    protected function budgetSumExpenses(): array
    {
        $eur    = TransactionCurrency::where('code', 'EUR')->first();
        $usd    = TransactionCurrency::where('code', 'USD')->first();
        $data   = [];
        $amount = 400;
        try {
            $amount = random_int(100, 2500);
        } catch (Exception $e) {
            $e->getMessage();
        }
        $amount = bcmul((string)round($amount / 100, 2), '-1');

        foreach ([$eur, $usd] as $currency) {
            $data[$currency->id] = [
                'currency_id'             => $currency->id,
                'currency_name'           => $currency->name,
                'currency_symbol'         => $currency->symbol,
                'currency_code'           => $currency->code,
                'currency_decimal_places' => $currency->decimal_places,
                'sum'                     => $amount,
            ];
        }

        return $data;
    }

    /**
     * Method that returns default data for when the category OperationsController
     * "sumIncome" method is called.
     *
     * Also applies to NoCategoryRepos::sumIncome.
     *
     * @return array
     */
    protected function categorySumIncome(): array
    {
        $eur    = TransactionCurrency::where('code', 'EUR')->first();
        $usd    = TransactionCurrency::where('code', 'USD')->first();
        $data   = [];
        $amount = 400;
        try {
            $amount = random_int(100, 2500);
        } catch (Exception $e) {
            $e->getMessage();
        }
        $amount = (string)round($amount / 100, 2);

        foreach ([$eur, $usd] as $currency) {
            $data[$currency->id] = [
                'currency_id'             => $currency->id,
                'currency_name'           => $currency->name,
                'currency_symbol'         => $currency->symbol,
                'currency_code'           => $currency->code,
                'currency_decimal_places' => $currency->decimal_places,
                'sum'                     => $amount,
            ];
        }

        return $data;
    }

    /**
     * Method that returns default data for when the category NoCategoryRepos
     * "listExpenses" method is called.
     *
     * @return array
     */
    protected function noCategoryListExpenses(): array
    {
        $eur    = TransactionCurrency::where('code', 'EUR')->first();
        $usd    = TransactionCurrency::where('code', 'USD')->first();
        $data   = [];
        $amount = 400;
        $date   = null;
        try {
            $amount = random_int(100, 2500);
            $date   = new Carbon;
        } catch (Exception $e) {
            $e->getMessage();
        }
        $amount = bcmul((string)round($amount / 100, 2), '-1');

        foreach ([$eur, $usd] as $currency) {
            $data[$currency->id] = [
                'currency_id'             => $currency->id,
                'currency_name'           => $currency->name,
                'currency_symbol'         => $currency->symbol,
                'currency_code'           => $currency->code,
                'currency_decimal_places' => $currency->decimal_places,
                'transaction_journals'    => [],
            ];
            // add two random amounts:
            for ($i = 0; $i < 2; $i++) {
                $data[$currency->id]['transaction_journals'][$i] = [
                    'amount' => $amount,
                    'date'   => $date,
                ];
            }
        }

        return $data;
    }

    /**
     * Method that returns default data for when the category NoCategoryRepos
     * "listExpenses" method is called.
     *
     * @return array
     */
    protected function noCategoryListIncome(): array
    {
        $eur    = TransactionCurrency::where('code', 'EUR')->first();
        $usd    = TransactionCurrency::where('code', 'USD')->first();
        $data   = [];
        $amount = 400;
        $date   = null;
        try {
            $amount = random_int(100, 2500);
            $date   = new Carbon;
        } catch (Exception $e) {
            $e->getMessage();
        }
        $amount = (string)round($amount / 100, 2);

        foreach ([$eur, $usd] as $currency) {
            $data[$currency->id] = [
                'currency_id'             => $currency->id,
                'currency_name'           => $currency->name,
                'currency_symbol'         => $currency->symbol,
                'currency_code'           => $currency->code,
                'currency_decimal_places' => $currency->decimal_places,
                'transaction_journals'    => [],
            ];
            // add two random amounts:
            for ($i = 0; $i < 2; $i++) {
                $data[$currency->id]['transaction_journals'][$i] = [
                    'amount' => $amount,
                    'date'   => $date,
                ];
            }
        }

        return $data;
    }

}