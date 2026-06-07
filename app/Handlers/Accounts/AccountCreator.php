<?php

namespace App\Handlers\Accounts;

use App\Dto\AccountDto;
use App\Models\Account;
use App\Services\Accounts\AttachAccountToSignedInUser;
use App\Services\Accounts\EnableNotificationsForAccount;
use App\Services\Accounts\UpdateCreditCardBalance;
use Illuminate\Support\Facades\Schema;

readonly class AccountCreator
{
    public function __construct(
        private AttachAccountToSignedInUser $attachAccountToSignedUser,
        private UpdateCreditCardBalance $updateCreditCardBalance,
        private EnableNotificationsForAccount $enableNotificationsForAccount,
    ) {}

    public function execute(AccountDto $dto): Account
    {
        $payload = $dto->toModelArray();

        if (! Schema::hasColumn('accounts', 'feed_account_id')) {
            unset($payload['feed_account_id']);
        }

        $account = new Account;
        $account->fill(
            array_merge(
                $payload,
                ['user_id' => auth()->id()]
            )
        );
        $account->save();

        $this->attachAccountToSignedUser->execute($account);
        $this->updateCreditCardBalance->execute($account);
        $this->updateCreditCardBalance->execute($account);
        $this->enableNotificationsForAccount->execute($account);

        return $account;
    }
}
