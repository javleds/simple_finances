<?php

namespace App\Http\Requests\Api;

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
            'scheduled_at' => ['sometimes', 'required', 'date'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'status' => ['sometimes', 'required', 'in:pending,paid'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ];
    }
}
