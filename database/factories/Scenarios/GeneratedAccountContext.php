<?php

namespace Database\Factories\Scenarios;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Collection;

class GeneratedAccountContext
{
    public function __construct(
        public Account $account,
        public User $owner,
        public Collection $members,
        public bool $ownedBySubject,
    ) {}

    public function addMember(User $user): void
    {
        if ($this->members->contains(fn (User $member): bool => $member->is($user))) {
            return;
        }

        $this->members->push($user);
    }

    public function allUsers(): Collection
    {
        return $this->members->values();
    }
}
