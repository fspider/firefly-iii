<?php
/**
 * AssetAccounts.php
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

namespace FireflyIII\Import\Mapper;

use FireflyIII\Models\Account;
use FireflyIII\Models\AccountType;
use FireflyIII\Repositories\Account\AccountRepositoryInterface;

/**
 * Class AssetAccounts.
 */
class AssetAccounts implements MapperInterface
{
    /**
     * Get map of asset accounts.
     *
     * @return array
     */
    public function getMap(): array
    {
        /** @var AccountRepositoryInterface $accountRepository */
        $accountRepository = app(AccountRepositoryInterface::class);
        $set               = $accountRepository->getAccountsByType(
            [AccountType::DEFAULT, AccountType::ASSET, AccountType::LOAN, AccountType::DEBT, AccountType::CREDITCARD, AccountType::MORTGAGE,]
        );
        $list              = [];

        /** @var Account $account */
        foreach ($set as $account) {
            $accountId = (int)$account->id;
            $name      = $account->name;
            $iban      = $account->iban ?? '';
            if ('' !== $iban) {
                $name .= ' (' . $iban . ')';
            }

            // is a liability?
            if (in_array($account->accountType->type, [AccountType::LOAN, AccountType::DEBT, AccountType::CREDITCARD, AccountType::MORTGAGE], true)) {
                $name = trans('import.import_liability_select') . ': ' . $name;
            }

            $list[$accountId] = $name;
        }
        asort($list);
        $list = [0 => (string)trans('import.map_do_not_map')] + $list;

        return $list;
    }
}
