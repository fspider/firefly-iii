<?php
/**
 * SetSourceAccount.php
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

namespace FireflyIII\TransactionRules\Actions;

use FireflyIII\Models\Account;
use FireflyIII\Models\AccountType;
use FireflyIII\Models\RuleAction;
use FireflyIII\Models\TransactionJournal;
use FireflyIII\Models\TransactionType;
use FireflyIII\Repositories\Account\AccountRepositoryInterface;
use Log;

/**
 * Class SetSourceAccount.
 */
class SetSourceAccount implements ActionInterface
{
    /** @var RuleAction The rule action */
    private $action;

    /** @var TransactionJournal The journal */
    private $journal;

    /** @var Account The new source account */
    private $newSourceAccount;

    /** @var AccountRepositoryInterface Account repository */
    private $repository;

    /**
     * TriggerInterface constructor.
     *
     * @param RuleAction $action
     */
    public function __construct(RuleAction $action)
    {
        $this->action = $action;
    }

    /**
     * Set source account to X
     *
     * @param TransactionJournal $journal
     *
     * @return bool
     */
    public function act(TransactionJournal $journal): bool
    {
        $this->journal    = $journal;
        $this->repository = app(AccountRepositoryInterface::class);
        $this->repository->setUser($journal->user);
        // journal type:
        $type = $journal->transactionType->type;
        // if this is a transfer or a withdrawal, the new source account must be an asset account or a default account, and it MUST exist:
        if ((TransactionType::WITHDRAWAL === $type || TransactionType::TRANSFER === $type) && !$this->findAssetAccount()) {
            Log::error(
                sprintf(
                    'Cannot change source account of journal #%d because no asset account with name "%s" exists.',
                    $journal->id,
                    $this->action->action_value
                )
            );

            return false;
        }

        // if this is a deposit, the new source account must be a revenue account and may be created:
        if (TransactionType::DEPOSIT === $type) {
            $this->findRevenueAccount();
        }

        Log::debug(sprintf('New source account is #%d ("%s").', $this->newSourceAccount->id, $this->newSourceAccount->name));

        // update source transaction with new source account:
        // get source transaction:
        $transaction = $journal->transactions()->where('amount', '<', 0)->first();
        if (null === $transaction) {
            // @codeCoverageIgnoreStart
            Log::error(sprintf('Cannot change source account of journal #%d because no source transaction exists.', $journal->id));

            return false;
            // @codeCoverageIgnoreEnd
        }
        $transaction->account_id = $this->newSourceAccount->id;
        $transaction->save();
        $journal->touch();
        Log::debug(sprintf('Updated transaction #%d and gave it new account ID.', $transaction->id));

        return true;
    }

    /**
     * @return bool
     */
    private function findAssetAccount(): bool
    {
        $account = $this->repository->findByName($this->action->action_value, [AccountType::DEFAULT, AccountType::ASSET]);

        if (null === $account) {
            Log::debug(sprintf('There is NO asset account called "%s".', $this->action->action_value));

            return false;
        }
        Log::debug(sprintf('There exists an asset account called "%s". ID is #%d', $this->action->action_value, $account->id));
        $this->newSourceAccount = $account;

        return true;
    }

    /**
     *
     */
    private function findRevenueAccount(): void
    {
        $account = $this->repository->findByName($this->action->action_value, [AccountType::REVENUE]);
        if (null === $account) {
            // create new revenue account with this name:
            $data    = [
                'name'            => $this->action->action_value,
                'account_type'    => 'revenue',
                'account_type_id' => null,
                'virtual_balance' => 0,
                'active'          => true,
                'iban'            => null,
            ];
            $account = $this->repository->store($data);
        }
        Log::debug(sprintf('Found or created revenue account #%d ("%s")', $account->id, $account->name));
        $this->newSourceAccount = $account;
    }
}
