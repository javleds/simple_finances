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
            'notification_type_ids' => ['required', 'array'],
            'notification_type_ids.*' => ['integer', 'exists:notification_types,id'],
            'account_ids' => ['required', 'array'],
            'account_ids.*' => ['integer', 'exists:accounts,id'],
        ];
    }
}
