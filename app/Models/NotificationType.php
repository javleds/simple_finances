<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NotificationType extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationTypeFactory> */
    use HasFactory;

    public const INVITATION_NOTIFICATION = 'Invitación a cuentas compartidas';
    public const MOVEMENTS_NOTIFICATION = 'Movimientos en cuentas compartidas';
    public const WEEKLY_SUMMARY = 'Resumen semanal de cuentas compartidas';

    public const DEFAULT_NOTIFICATIONS = [
        self::INVITATION_NOTIFICATION,
        self::MOVEMENTS_NOTIFICATION,
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notification_setups');
    }

    public function scopeDefaults(): void
    {
        $this->whereIn('name', self::DEFAULT_NOTIFICATIONS);
    }
}
