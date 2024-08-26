<?php

namespace App\Models;

use App\Enums\InviteStatus;
use App\Events\AccountInviteCreated;
use App\Events\AccountInviteCreatingRequested;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountInvite extends Model
{
    use HasFactory;

    protected $dispatchesEvents = [
        'creating' => AccountInviteCreatingRequested::class,
        'created' => AccountInviteCreated::class,
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'status' => InviteStatus::class,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function unscopedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class)->withoutGlobalScopes();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unscopedUser(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function isPending(): bool
    {
        return $this->status === InviteStatus::Pending;
    }

    public function isAccepted(): bool
    {
        return $this->status === InviteStatus::Accepted;
    }

    public function isDeclined(): bool
    {
        return $this->status === InviteStatus::Declined;
    }

    public function isOwnerAccount(): bool
    {
        return $this->user_id === auth()->id();
    }
}
