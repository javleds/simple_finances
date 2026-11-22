<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\DecimalAmount;
use Illuminate\Foundation\Http\FormRequest;

class SubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'amount'          => ['bail', 'required', new DecimalAmount(minimum: '0.01', maximum: '999999.99')],
            'started_at'      => ['required', 'date'],
            'finished_at'     => ['nullable', 'date', 'after_or_equal:started_at'],
            'frequency_unit'  => ['required', 'integer', 'min:1'],
            'frequency_type'  => ['required', 'in:days,months,years'],
            'feed_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ];
    }
}
