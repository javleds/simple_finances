<?php

namespace App\Dto;

use App\Enums\InviteStatus;
use App\Models\Account;
use App\Models\AccountInvite;
use App\Models\User;

readonly class AccountInviteDto
{
    public function __construct(
        public Account $account,
        public User $owner,
        public string $email,
        public float $percentage,
        public InviteStatus $status = InviteStatus::Pending,
    ) {}

    public function toModelAttributes(): array
    {
        return [
            'account_id' => $this->account->id,
            'user_id' => $this->owner->id,
            'email' => $this->email,
            'percentage' => $this->percentage,
            'status' => $this->status,
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
