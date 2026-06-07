<?php

namespace App\Handlers\Accounts;

use App\Dto\AccountDto;
use App\Models\Account;
use App\Services\Accounts\UpdateCreditCardBalance;
use Illuminate\Support\Facades\Schema;

readonly class AccountEditor
{
    public function __construct(
        private UpdateCreditCardBalance $updateCreditCardBalance,
    ) {}

    public function execute(Account $account, AccountDto $dto): Account
    {
        $payload = $dto->toModelArray();

        if (! Schema::hasColumn('accounts', 'feed_account_id')) {
            unset($payload['feed_account_id']);
        }

        $account->update($payload);

        $this->updateCreditCardBalance->execute($account);

        return $account;
    }
}
