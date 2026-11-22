<?php

declare(strict_types=1);

namespace App\Dto;

use FinSi\FinanceDomain\Enums\InviteStatus;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\User;

readonly class AccountInviteDto
{
    public function __construct(
        public Account $account,
        public User $owner,
        public string $email,
        public string $percentage,
        public InviteStatus $status = InviteStatus::Pending,
    ) {
    }

    public function toModelAttributes(): array
    {
        return [
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
            'email'      => $this->email,
            'percentage' => $this->percentage,
            'status'     => $this->status,
        ];
    }

    public function toInvite(): AccountInvite
    {
        $invite = new AccountInvite($this->toModelAttributes());
        $invite->setRelation('account', $this->account);
        $invite->setRelation('user', $this->owner);

        return $invite;
    }
}
