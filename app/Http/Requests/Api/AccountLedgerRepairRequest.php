<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountLedgerRepairRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'diagnostic_id' => ['nullable', 'string', 'max:255'],
            'issue_code' => ['required', 'string', 'max:80'],
            'repair_type' => ['required', Rule::in(['settlement_correction', 'custody_correction'])],
            'from_user_id' => ['required_if:repair_type,settlement_correction', 'integer', 'exists:users,id'],
            'to_user_id' => ['required_if:repair_type,settlement_correction', 'integer', 'exists:users,id', 'different:from_user_id'],
            'user_id' => ['required_if:repair_type,custody_correction', 'integer', 'exists:users,id'],
            'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'description' => ['required', 'string', 'max:255'],
            'evidence' => ['nullable', 'array'],
            'preview' => ['nullable', 'array'],
        ];
    }
}
