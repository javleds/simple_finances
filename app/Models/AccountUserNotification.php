<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountUserNotification extends Model
{
    /** @use HasFactory<\Database\Factories\AccountUserNotificationFactory> */
    use HasFactory;

    public function users(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accounts(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function notificationTypes(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class);
    }
}
