<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class NotificationSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notification_type_ids' => ['present', 'array'],
            'notification_type_ids.*' => ['integer', 'exists:notification_types,id'],
            'account_ids' => ['present', 'array'],
            'account_ids.*' => ['integer', 'exists:accounts,id'],
        ];
    }
}
