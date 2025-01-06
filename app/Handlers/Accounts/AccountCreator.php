<?php

namespace App\Handlers\Accounts;

use App\Dto\AccountDto;
use App\Models\Account;
use App\Services\Accounts\AttachAccountToSignedInUser;
use App\Services\Accounts\UpdateCreditCardBalance;

readonly class AccountCreator
{
    public function __construct(
        private AttachAccountToSignedInUser $attachAccountToSignedUser,
        private UpdateCreditCardBalance $updateCreditCardBalance,
    ) {}

    public function execute(AccountDto $dto): Account
    {
        $account = new Account();
        $account->fill(
            array_merge(
                $dto->toModelArray(),
                ['user_id' => auth()->id()]
            )
        );
        $account->save();

        $this->attachAccountToSignedUser->execute($account);
        $this->updateCreditCardBalance->execute($account);

        return $account;
    }
}
