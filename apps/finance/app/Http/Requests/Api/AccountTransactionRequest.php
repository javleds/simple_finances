<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\DecimalAmount;

use function array_column;

use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Foundation\Http\FormRequest;

class AccountTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cash_source'                => ['sometimes', 'in:personal,external,pending'],
            'uses_shared_account_rules'  => ['prohibited'],
            'type'                       => ['required', 'in:income,outcome'],
            'status'                     => ['required', 'in:completed'],
            'category_id'                => ['nullable', 'integer', 'exists:categories,id'],
            'concept'                    => ['required', 'string', 'max:255'],
            'amount'                     => ['bail', 'required', new DecimalAmount(minimum: '0', maximum: '99999999.99', exclusiveMinimum: true)],
            'paid_by_user_id'            => ['nullable', 'integer', 'exists:users,id'],
            'custodian_user_id'          => ['nullable', 'integer', 'exists:users,id'],
            'payment_source'             => ['nullable', 'in:account_fund,member_out_of_pocket'],
            'split_between_users'        => ['nullable', 'boolean'],
            'user_payments'              => ['required_if:split_between_users,true', 'nullable', 'array'],
            'user_payments.*.user_id'    => ['required_with:user_payments', 'integer', 'exists:users,id'],
            'user_payments.*.percentage' => ['bail', 'required_with:user_payments', new DecimalAmount(minimum: '0', maximum: '100')],
            'scheduled_at'               => ['required', 'date'],
            'financial_goal_id'          => ['nullable', 'integer', 'exists:financial_goals,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->boolean('split_between_users')) {
                return;
            }

            if ('outcome' !== $this->input('type')) {
                $validator->errors()->add('split_between_users', 'Only outcome transactions can be split between users.');

                return;
            }

            $total = Decimal::sum(array_column($this->input('user_payments', []) ?? [], 'percentage'));

            if (0 !== Decimal::compare($total, '100.00')) {
                $validator->errors()->add('user_payments', 'The user payment percentages must add up to 100.');
            }
        });
    }
}
