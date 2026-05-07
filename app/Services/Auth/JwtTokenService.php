<?php

namespace App\Services\Auth;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use RuntimeException;

class JwtTokenService
{
    public function __construct(
        private readonly Repository $cache,
    ) {}

    public function generate(User $user): array
    {
        $issuedAt = CarbonImmutable::now();
        $expiresAt = $issuedAt->addMinutes($this->ttlMinutes());
        $payload = [
            'sub' => $user->id,
            'iat' => $issuedAt->timestamp,
            'exp' => $expiresAt->timestamp,
            'jti' => (string) str()->uuid(),
        ];

        return [
            'token' => $this->encode($payload),
            'expires_at' => $expiresAt->toIso8601String(),
            'token_type' => 'Bearer',
        ];
    }

    public function parse(string $token): array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw new RuntimeException('Invalid token.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;

        $signature = $this->base64UrlDecode($encodedSignature);
        $expectedSignature = hash_hmac(
            'sha256',
            $encodedHeader.'.'.$encodedPayload,
            $this->secret(),
            true,
        );

        if (! hash_equals($expectedSignature, $signature)) {
            throw new RuntimeException('Invalid signature.');
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (! is_array($payload) || ! isset($payload['sub'], $payload['jti'], $payload['exp'])) {
            throw new RuntimeException('Invalid payload.');
        }

        if (CarbonImmutable::now()->timestamp >= (int) $payload['exp']) {
            throw new RuntimeException('Expired token.');
        }

        if ($this->isRevoked((string) $payload['jti'])) {
            throw new RuntimeException('Revoked token.');
        }

        return $payload;
    }

    public function revoke(array $payload): void
    {
        $expiresAt = CarbonImmutable::createFromTimestamp((int) $payload['exp']);
        $seconds = max(1, CarbonImmutable::now()->diffInSeconds($expiresAt, false));

        $this->cache->put($this->revocationKey((string) $payload['jti']), true, $seconds);
    }

    private function isRevoked(string $jti): bool
    {
        return (bool) $this->cache->get($this->revocationKey($jti), false);
    }

    private function encode(array $payload): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac(
            'sha256',
            $encodedHeader.'.'.$encodedPayload,
            $this->secret(),
            true,
        );

        return $encodedHeader.'.'.$encodedPayload.'.'.$this->base64UrlEncode($signature);
    }

    private function secret(): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('APP_KEY is required.');
        }

        if (str_starts_with($key, 'base64:')) {
            return (string) base64_decode(substr($key, 7), true);
        }

        return $key;
    }

    private function ttlMinutes(): int
    {
        return (int) env('API_JWT_TTL_MINUTES', 10080);
    }

    private function revocationKey(string $jti): string
    {
        return 'api_jwt_revoked:'.$jti;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('Invalid token encoding.');
        }

        return $decoded;
    }
}
