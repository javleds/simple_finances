<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountId = $this->route('account')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:9'],
            'description' => ['nullable', 'string'],
            'virtual' => ['required', 'boolean'],
            'credit_card' => ['required', 'boolean'],
            'credit_line' => ['nullable', 'numeric', 'min:0', 'required_if:credit_card,true'],
            'cutoff_day' => ['nullable', 'integer', 'min:1', 'max:31', 'required_if:credit_card,true'],
            'feed_account_id' => ['nullable', 'integer', 'different:id', 'exists:accounts,id'],
        ];
    }
}
