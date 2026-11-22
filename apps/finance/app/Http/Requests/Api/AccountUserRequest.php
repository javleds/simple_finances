<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\DecimalAmount;
use Illuminate\Foundation\Http\FormRequest;

class AccountUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'    => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'percentage' => ['bail', 'required', new DecimalAmount(minimum: '0', maximum: '100')],
        ];
    }
}
