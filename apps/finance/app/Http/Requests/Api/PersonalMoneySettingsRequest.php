<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\DecimalAmount;
use Illuminate\Foundation\Http\FormRequest;

class PersonalMoneySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id'      => ['required', 'integer', 'exists:accounts,id'],
            'started_at'      => 'now' === $this->input('start_mode')
                ? ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:yesterday', 'before_or_equal:tomorrow']
                : ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'opening_balance' => ['bail', 'required', new DecimalAmount(minimum: '-999999999999.99', maximum: '999999999999.99')],
            'start_mode'      => ['sometimes', 'in:now,date'],
            'operation_key'   => ['required_if:start_mode,now', 'nullable', 'uuid'],
            'enabled'         => ['required', 'boolean'],
        ];
    }
}
