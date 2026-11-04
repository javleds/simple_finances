<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AccountMemberTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_user_id' => ['required', 'integer', 'exists:users,id'],
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'action_type' => ['nullable', 'in:user_to_user,custody_to_user,user_to_account'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
