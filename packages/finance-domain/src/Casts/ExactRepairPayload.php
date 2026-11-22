<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Casts;

use Brick\Math\BigDecimal;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

final class ExactRepairPayload implements CastsAttributes
{
    private const MONEY_FIELDS = [
        'amount', 'balance', 'custody_amount', 'settlement_amount', 'open_amount',
        'balance_before', 'balance_after', 'delta', 'percentage',
    ];

    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $previous = null;
        $field = null;
        $lastString = null;
        $json = preg_replace_callback(
            '/"(?:\\\\.|[^"\\\\])*"|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?|[{}\[\]:,]|true|false|null/s',
            static function (array $match) use (&$previous, &$field, &$lastString): string {
                $token = $match[0];
                $replacement = $token;

                if ($token[0] === '"') {
                    $lastString = json_decode($token, true, 512, JSON_THROW_ON_ERROR);
                }

                if ($token === ':') {
                    $field = $lastString;
                }

                if ($previous === ':' && in_array($field, self::MONEY_FIELDS, true)
                    && preg_match('/^-?[0-9]/', $token)) {
                    $replacement = json_encode((string) BigDecimal::of($token)->toScale(2), JSON_THROW_ON_ERROR);
                }

                $previous = $token;

                return $replacement;
            },
            $value,
        );

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $payload === null ? null : $this->canonicalize($payload);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($this->canonicalize($value), JSON_THROW_ON_ERROR);
    }

    private function canonicalize(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->canonicalize($value);
                continue;
            }

            if ($value !== null && in_array($key, self::MONEY_FIELDS, true)) {
                $payload[$key] = Decimal::normalize($value);
            }
        }

        return $payload;
    }
}
