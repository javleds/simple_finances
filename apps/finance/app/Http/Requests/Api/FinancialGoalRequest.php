<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\DecimalAmount;
use Illuminate\Foundation\Http\FormRequest;

class FinancialGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id'        => ['required', 'integer', 'exists:accounts,id'],
            'name'              => ['required', 'string', 'max:255'],
            'amount'            => ['bail', 'required', new DecimalAmount(minimum: '0', maximum: '99999999.99')],
            'must_completed_at' => ['nullable', 'date'],
            'status'            => ['sometimes', 'in:in progress,completed'],
        ];
    }
}
