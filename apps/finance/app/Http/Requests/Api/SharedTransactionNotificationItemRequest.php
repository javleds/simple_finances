<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\DecimalAmount;
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
            'batch_id'       => ['required', 'integer', 'exists:shared_transaction_notification_batches,id'],
            'account_id'     => ['nullable', 'integer', 'exists:accounts,id'],
            'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'modifier_id'    => ['required', 'integer', 'exists:users,id'],
            'action'         => ['required', 'in:created,updated,deleted,settled'],
            'concept'        => ['required', 'string', 'max:255'],
            'type'           => ['required', 'in:income,outcome'],
            'amount'         => ['bail', 'required', new DecimalAmount(minimum: '0', maximum: '99999999999999999.99')],
            'scheduled_at'   => ['required', 'date'],
        ];
    }
}
