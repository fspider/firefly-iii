<?php
/**
 * ListTransactionsRequest.php
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

namespace FireflyIII\Services\Spectre\Request;

use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Services\Spectre\Object\Account;
use FireflyIII\Services\Spectre\Object\Transaction;
use Log;

/**
 * Class ListTransactionsRequest
 * @codeCoverageIgnore
 */
class ListTransactionsRequest extends SpectreRequest
{
    /** @var Account */
    private $account;
    /** @var array */
    private $transactions = [];

    /**
     * @throws FireflyException
     *
     */
    public function call(): void
    {
        $hasNextPage = true;
        $nextId      = 0;
        while ($hasNextPage) {
            Log::debug(sprintf('Now calling ListTransactionsRequest for next_id %d', $nextId));
            $parameters = ['from_id' => $nextId, 'account_id' => $this->account->getId()];
            $uri        = '/api/v4/transactions?' . http_build_query($parameters);
            $response   = $this->sendSignedSpectreGet($uri, []);

            // count entries:
            Log::debug(sprintf('Found %d entries in data-array', count($response['data'])));

            // extract next ID
            $hasNextPage = false;
            if (isset($response['meta']['next_id']) && (int)$response['meta']['next_id'] > $nextId) {
                $hasNextPage = true;
                $nextId      = $response['meta']['next_id'];
                Log::debug(sprintf('Next ID is now %d.', $nextId));
            }

            // store customers:
            foreach ($response['data'] as $transactionArray) {
                $this->transactions[] = new Transaction($transactionArray);
            }
        }
    }

    /**
     * @return array
     */
    public function getTransactions(): array
    {
        return $this->transactions;
    }


    /**
     * @param Account $account
     */
    public function setAccount(Account $account): void
    {
        $this->account = $account;
    }


}
