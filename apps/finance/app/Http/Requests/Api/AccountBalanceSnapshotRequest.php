<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\DecimalAmount;
use Illuminate\Foundation\Http\FormRequest;

class AccountBalanceSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'observed_balance' => ['bail', 'required', new DecimalAmount(minimum: '0', maximum: '9999999999.99')],
            'observed_at'      => ['required', 'date'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ];
    }
}
