<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;

use function is_string;

class InvitableEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (User::query()->where('email', $value)->whereNotNull('virtual_account_id')->exists()) {
            $fail('Los custodios virtuales no pueden recibir invitaciones.');
        }
    }
}
