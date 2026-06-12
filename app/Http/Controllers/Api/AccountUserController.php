<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\BulkUpdateAccountUsersRequest;
use App\Http\Requests\Api\AccountUserRequest;
use App\Models\Account;
use App\Models\User;
use App\Services\Accounts\UpdateAccountUsersPercentages;
use App\Services\Api\AuthorizeAccountAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountUserController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
    ) {}

    public function index(Account $account, Request $request): JsonResponse
    {
        $this->ensureOwner($account);

        $relation = $account->users()->withPivot('percentage')->orderBy('users.name');

        if ($request->filled('percentage')) {
            $relation->wherePivot('percentage', (float) $request->query('percentage'));
        }

        return $this->respondPaginated($relation->getQuery(), $request);
    }

    public function store(Account $account, AccountUserRequest $request): JsonResponse
    {
        $this->ensureOwner($account);

        $user = User::withoutGlobalScopes()->findOrFail($request->integer('user_id'));

        $account->users()->syncWithoutDetaching([
            $user->id => [
                'percentage' => $request->float('percentage'),
            ],
        ]);

        return $this->respondModel(
            $account->users()->withPivot('percentage')->findOrFail($user->id),
            [],
            201,
        );
    }

    public function show(Account $account, User $user): JsonResponse
    {
        $this->ensureOwner($account);

        return $this->respondModel(
            $account->users()->withPivot('percentage')->findOrFail($user->id)
        );
    }

    public function update(Account $account, User $user, AccountUserRequest $request): JsonResponse
    {
        $this->ensureOwner($account);
        $this->ensureAttached($account, $user);

        $account->users()->updateExistingPivot($user->id, [
            'percentage' => $request->float('percentage'),
        ]);

        return $this->respondModel(
            $account->users()->withPivot('percentage')->findOrFail($user->id)
        );
    }

    public function bulkUpdate(
        Account $account,
        BulkUpdateAccountUsersRequest $request,
        UpdateAccountUsersPercentages $updateAccountUsersPercentages,
    ): JsonResponse {
        $this->ensureOwner($account);

        $users = $updateAccountUsersPercentages->execute(
            $account,
            $request->normalizedUsers(),
        );

        return $this->respond([
            'data' => $users,
            'meta' => [
                'account_id' => $account->id,
                'total_percentage' => round(
                    collect($request->normalizedUsers())->sum('percentage'),
                    2,
                ),
            ],
        ]);
    }

    public function delete(Account $account, User $user): JsonResponse
    {
        $this->ensureOwner($account);
        $this->ensureAttached($account, $user);

        $account->users()->detach($user->id);

        return $this->respond([
            'message' => 'Account user deleted successfully.',
        ]);
    }

    private function ensureOwner(Account $account): void
    {
        $this->authorizeAccountAccess->ensureOwner($account);
    }

    private function ensureAttached(Account $account, User $user): void
    {
        $this->authorizeAccountAccess->ensureAccountUser($account, $user);
    }
}
