<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:income,outcome'],
            'status' => ['required', 'in:pending,completed'],
            'concept' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'split_between_users' => ['nullable', 'boolean'],
            'user_payments' => ['nullable', 'array'],
            'user_payments.*.user_id' => ['required_with:user_payments', 'integer', 'exists:users,id'],
            'user_payments.*.percentage' => ['required_with:user_payments', 'numeric', 'min:0', 'max:100'],
            'scheduled_at' => ['required', 'date'],
            'financial_goal_id' => ['nullable', 'integer', 'exists:financial_goals,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->boolean('split_between_users')) {
                return;
            }

            if ($this->input('type') !== 'outcome') {
                $validator->errors()->add('split_between_users', 'Only outcome transactions can be split between users.');

                return;
            }

            $total = collect($this->input('user_payments', []))
                ->sum(fn (array $item): float => (float) ($item['percentage'] ?? 0.0));

            if (round($total, 2) !== 100.0) {
                $validator->errors()->add('user_payments', 'The user payment percentages must add up to 100.');
            }
        });
    }
}
