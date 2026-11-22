<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\DecimalAmount;
use Illuminate\Foundation\Http\FormRequest;

class RetireAccountCustodianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'distributions'           => ['present', 'array', 'max:100'],
            'distributions.*'         => ['required', 'array:user_id,amount'],
            'distributions.*.user_id' => ['required', 'integer', 'distinct'],
            'distributions.*.amount'  => ['bail', 'required', new DecimalAmount(minimum: '0', maximum: '999999999.99')],
        ];
    }
}
