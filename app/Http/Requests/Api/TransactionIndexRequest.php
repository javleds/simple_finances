<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TransactionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $categories = $this->input('category_ids');
        if (is_string($categories)) {
            $this->merge(['category_ids' => explode(',', $categories)]);
        }
    }

    public function rules(): array
    {
        return [
            'category_ids' => ['sometimes', 'array', 'max:100'],
            'category_ids.*' => ['required', 'integer', 'min:1'],
            'uncategorized' => ['sometimes', 'boolean'],
            'has_attachments' => ['sometimes', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
