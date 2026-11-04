<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use function now;
use function random_int;
use function str_pad;

use const STR_PAD_LEFT;

class TelegramVerificationCode extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('used_at')
            ->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeUsed(Builder $query): Builder
    {
        return $query->whereNotNull('used_at');
    }

    public function isValid(): bool
    {
        return null === $this->used_at && $this->expires_at > now();
    }

    public function isExpired(): bool
    {
        return $this->expires_at <= now();
    }

    public function isUsed(): bool
    {
        return null !== $this->used_at;
    }

    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('code', $code)->valid()->exists());

        return $code;
    }

    public static function createForUser(User $user, int $expirationMinutes = 10): self
    {
        return self::create([
            'user_id'    => $user->id,
            'code'       => self::generateUniqueCode(),
            'expires_at' => now()->addMinutes($expirationMinutes),
        ]);
    }

    protected $fillable = [
        'user_id',
        'code',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at'    => 'datetime',
        ];
    }
}
