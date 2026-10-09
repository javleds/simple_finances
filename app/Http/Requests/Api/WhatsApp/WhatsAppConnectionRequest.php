<?php

namespace App\Http\Requests\Api\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class WhatsAppConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['code' => ['required', 'string', 'regex:/^[0-9]{6}$/D']];
    }
}
