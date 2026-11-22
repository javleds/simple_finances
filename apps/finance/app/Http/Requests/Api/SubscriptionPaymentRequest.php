<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\DecimalAmount;
use Illuminate\Foundation\Http\FormRequest;

class SubscriptionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subscription_id' => ['required_without:status', 'nullable', 'integer', 'exists:subscriptions,id'],
            'scheduled_at'    => ['sometimes', 'required', 'date'],
            'amount'          => ['bail', 'sometimes', 'required', new DecimalAmount(minimum: '0.01', maximum: '99999999.99')],
            'status'          => ['sometimes', 'required', 'in:pending,paid'],
            'account_id'      => ['nullable', 'integer', 'exists:accounts,id'],
        ];
    }
}
