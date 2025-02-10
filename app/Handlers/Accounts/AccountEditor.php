<?php

namespace App\Handlers\Accounts;

use App\Dto\AccountDto;
use App\Models\Account;
use App\Services\Accounts\AttachAccountToSignedInUser;
use App\Services\Accounts\UpdateCreditCardBalance;

readonly class AccountEditor
{
    public function __construct(
        private UpdateCreditCardBalance $updateCreditCardBalance,
    ) {}

    public function execute(Account $account, AccountDto $dto): Account
    {
        $account->update($dto->toModelArray());

        $this->updateCreditCardBalance->execute($account);

        return $account;
    }
}
