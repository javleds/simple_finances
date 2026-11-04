<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\Legal\LegalAcceptanceRequest;

use function array_merge;

class RegisterRequest extends LegalAcceptanceRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
            'phone_number'     => ['nullable', 'string', 'max:255'],
            'post_auth_action' => ['nullable', 'string', 'in:account-invites'],
        ]);
    }
}
