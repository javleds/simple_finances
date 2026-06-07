<?php

namespace App\Http\Requests\Api;

use App\Models\Account;
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
            'users' => ['required', 'array', 'min:1'],
            'users.*.user_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'users.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
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

            $totalPercentage = round(
                collect($normalizedUsers)->sum('percentage'),
                2,
            );

            if ($totalPercentage !== 100.0) {
                $validator->errors()->add('users', 'The user percentages must add up to 100.00.');
            }
        });
    }

    public function normalizedUsers(): array
    {
        return collect($this->input('users', []))
            ->map(fn (array $user): array => [
                'user_id' => (int) $user['user_id'],
                'percentage' => round((float) $user['percentage'], 2),
            ])
            ->all();
    }
}
