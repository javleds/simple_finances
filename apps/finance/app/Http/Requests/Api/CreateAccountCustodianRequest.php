<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateAccountCustodianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:100'],
            'opening_balance'     => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
            'transfers'           => ['sometimes', 'array', 'max:100'],
            'transfers.*'         => ['required', 'array:user_id,amount'],
            'transfers.*.user_id' => ['required', 'integer', 'distinct'],
            'transfers.*.amount'  => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
        ];
    }
}
