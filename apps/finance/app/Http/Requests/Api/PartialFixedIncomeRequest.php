<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\DecimalAmount;
use Illuminate\Foundation\Http\FormRequest;

class PartialFixedIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fixed_income_id' => ['required', 'integer', 'exists:fixed_incomes,id'],
            'name'            => ['required', 'string', 'max:255'],
            'amount'          => ['bail', 'required', new DecimalAmount(minimum: '0', maximum: '99999999.99')],
        ];
    }
}
