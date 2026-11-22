<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\DecimalAmount;

use function array_column;
use function collect;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateAccountUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'users'              => ['required', 'array', 'min:1'],
            'users.*.user_id'    => ['required', 'integer', 'distinct', 'exists:users,id'],
            'users.*.percentage' => ['bail', 'required', new DecimalAmount(minimum: '0', maximum: '100')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Account $account */
            $account = $this->route('account');

            $normalizedUsers = $this->normalizedUsers();
            $requestedUserIds = collect($normalizedUsers)
                ->pluck('user_id')
                ->sort()
                ->values();
            $accountUserIds = $account->users()
                ->pluck('users.id')
                ->sort()
                ->values();

            if ($requestedUserIds->all() !== $accountUserIds->all()) {
                $validator->errors()->add('users', 'The request must include exactly the users attached to the account.');
            }

            $totalPercentage = Decimal::sum(array_column($normalizedUsers, 'percentage'));

            if (0 !== Decimal::compare($totalPercentage, '100.00')) {
                $validator->errors()->add('users', 'The user percentages must add up to 100.00.');
            }
        });
    }

    public function normalizedUsers(): array
    {
        return collect($this->input('users', []))
            ->map(fn (array $user): array => [
                'user_id'    => (int) $user['user_id'],
                'percentage' => Decimal::normalize($user['percentage']),
            ])
            ->all();
    }
}
