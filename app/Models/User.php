<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'telegram_chat_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class)->withPivot(['percentage']);
    }

    public function notificationTypes(): BelongsToMany
    {
        return $this->belongsToMany(NotificationType::class, 'notification_setups');
    }

    public function notificableAccounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_user_notifications');
    }

    public function telegramVerificationCodes(): HasMany
    {
        return $this->hasMany(TelegramVerificationCode::class);
    }

    public function hasTelegramLinked(): bool
    {
        return !empty($this->telegram_chat_id);
    }

    public function getTelegramUsername(): ?string
    {
        return $this->telegram_chat_id ? '@' . $this->name : null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function canReceiveNotification(string $name): bool
    {
        return $this->notificationTypes()->where('name', $name)->exists();
    }
}
