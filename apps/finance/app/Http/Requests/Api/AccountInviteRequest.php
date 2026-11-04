<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AccountInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required_without:status', 'nullable', 'integer', 'exists:accounts,id'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'percentage' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', 'required', 'in:pending,accepted,declined'],
        ];
    }
}
