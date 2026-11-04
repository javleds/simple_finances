<?php

namespace App\Http\Requests\Api\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class WhatsAppVerificationCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['phone_number' => ['required', 'string', 'regex:/^\+52[0-9]{10}$/D']];
    }
}
