<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SharedTransactionNotificationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'integer', 'exists:shared_transaction_notification_batches,id'],
            'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'modifier_id' => ['required', 'integer', 'exists:users,id'],
            'action' => ['required', 'in:created,updated,deleted'],
            'concept' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,outcome'],
            'amount' => ['required', 'numeric', 'min:0'],
            'scheduled_at' => ['required', 'date'],
        ];
    }
}
