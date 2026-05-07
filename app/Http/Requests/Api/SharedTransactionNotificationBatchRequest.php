<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SharedTransactionNotificationBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'status' => ['required', 'in:pending,processing,sent'],
            'window_started_at' => ['required', 'date'],
            'last_activity_at' => ['required', 'date'],
            'sent_at' => ['nullable', 'date'],
        ];
    }
}
