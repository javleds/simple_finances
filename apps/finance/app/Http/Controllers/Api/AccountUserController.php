<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use function abort_if;
use function abort_unless;
use function app;

use App\Http\Requests\Api\AccountUserRequest;
use App\Http\Requests\Api\BulkUpdateAccountUsersRequest;
use App\Services\Accounts\BuildAccountMemberSummary;
use App\Services\Accounts\RemoveAccountUser;
use App\Services\Accounts\UpdateAccountUserPercentage;
use App\Services\Accounts\UpdateAccountUsersPercentages;

use function array_column;
use function auth;
use function collect;

use FinSi\FinanceDomain\Authorization\AuthorizeAccountAccess;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountUserController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly BuildAccountMemberSummary $buildAccountMemberSummary,
    ) {
    }

    public function index(Account $account, Request $request): JsonResponse
    {
        $this->authorizeAccountAccess->ensureMember($account, (int) auth()->id());

        $relation = $account->users()->withPivot('percentage')->orderBy('users.name');

        if ($request->filled('percentage')) {
            $request->validate(['percentage' => ['bail', 'required', new \App\Rules\DecimalAmount(maximum: '100.00')]]);
            $relation->wherePivot('percentage', Decimal::normalize($request->query('percentage')));
        }

        $response = $this->respondPaginated($relation, $request, searchColumns: ['users.name', 'users.email']);
        $payload = $response->getData(true);
        $memberSummary = $this->buildAccountMemberSummary->execute($account);
        $summaryByUserId = collect($memberSummary['settlements_by_user'])
            ->keyBy(fn (array $item): string => (string) $item['user_id']);
        $custodyByUserId = collect($memberSummary['custody_by_user'])
            ->keyBy(fn (array $item): string => (string) $item['user_id']);

        $payload['data'] = collect($payload['data'])
            ->map(function (array $user) use ($summaryByUserId, $custodyByUserId): array {
                $userId = (string) $user['id'];
                $user['settlement_amount'] = $summaryByUserId->get($userId)['amount'] ?? '0.00';
                $user['custody_amount'] = $custodyByUserId->get($userId)['amount'] ?? '0.00';

                return $user;
            })
            ->values()
            ->all();

        return $this->respond($payload);
    }

    public function store(
        Account $account,
        AccountUserRequest $request,
        UpdateAccountUserPercentage $updateAccountUserPercentage,
    ): JsonResponse {
        $this->ensureOwner($account);

        $user = User::withoutGlobalScopes()->whereNull('virtual_account_id')->findOrFail($request->integer('user_id'));

        \Illuminate\Support\Facades\DB::transaction(function () use ($account, $user, $request, $updateAccountUserPercentage): void {
            Account::withoutGlobalScopes()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            if ($user->id !== $account->user_id) {
                app(\App\Services\Categories\ConvertAccountCategoryCatalog::class)->execute($account);
            }
            $account->users()->syncWithoutDetaching([$user->id => ['percentage' => '0.00']]);
            $updateAccountUserPercentage->execute($account, $user->id, Decimal::normalize($request->validated('percentage') ?? '0.00'));
        });

        return $this->respondModel(
            $account->users()->withPivot('percentage')->findOrFail($user->id),
            [],
            201,
        );
    }

    public function show(Account $account, User $user): JsonResponse
    {
        $this->authorizeAccountAccess->ensureMember($account, (int) auth()->id());

        return $this->respondModel(
            $account->users()->withPivot('percentage')->findOrFail($user->id)
        );
    }

    public function update(
        Account $account,
        User $user,
        AccountUserRequest $request,
        UpdateAccountUserPercentage $updateAccountUserPercentage,
    ): JsonResponse {
        $this->ensureOwner($account);
        $this->ensureAttached($account, $user);

        $updateAccountUserPercentage->execute($account, $user->id, Decimal::normalize($request->validated('percentage') ?? '0.00'));

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
                'account_id'       => $account->id,
                'total_percentage' => Decimal::sum(array_column($request->normalizedUsers(), 'percentage')),
            ],
        ]);
    }

    public function delete(Account $account, User $user, RemoveAccountUser $removeAccountUser): JsonResponse
    {
        $this->ensureCanRemoveUser($account, $user);
        $this->ensureAttached($account, $user);

        $removeAccountUser->execute($account, $user);

        return $this->respondDeleted('Account user deleted successfully.');
    }

    private function ensureOwner(Account $account): void
    {
        $this->authorizeAccountAccess->ensureOwner($account, (int) auth()->id());
    }

    private function ensureAttached(Account $account, User $user): void
    {
        $this->authorizeAccountAccess->ensureAccountUser($account, $user);
    }

    private function ensureCanRemoveUser(Account $account, User $user): void
    {
        $currentUserId = (int) auth()->id();

        if ((int) $account->user_id === $currentUserId) {
            abort_if((int) $user->id === $currentUserId, 422, 'Account owners cannot leave their own account.');

            return;
        }

        abort_unless((int) $user->id === $currentUserId, 403);
        $this->ensureUserCanLeaveAccount($account, $user);
    }

    private function ensureUserCanLeaveAccount(Account $account, User $user): void
    {
        $member = $account->users()
            ->withPivot('percentage')
            ->where('users.id', $user->id)
            ->first();

        if (! $member) {
            return;
        }

        abort_if(0 !== Decimal::compare($member->pivot->percentage, '0.00'), 422, 'You cannot leave an account while you still have an assigned percentage.');

        $summary = $this->buildAccountMemberSummary->execute($account);
        $custodyAmount = $this->summaryAmountForUser($summary['custody_by_user'], $user->id);
        $settlementAmount = $this->summaryAmountForUser($summary['settlements_by_user'], $user->id);

        abort_if(0 !== Decimal::compare($custodyAmount, '0.00'), 422, 'You cannot leave an account while you still have custody.');
        abort_if(0 !== Decimal::compare($settlementAmount, '0.00'), 422, 'You cannot leave an account while you still have pending reimbursements.');
    }

    /**
     * @param array<int, array{user_id: int|string, amount: string}> $items
     */
    private function summaryAmountForUser(array $items, int $userId): string
    {
        foreach ($items as $item) {
            if ((int) $item['user_id'] === $userId) {
                return Decimal::normalize($item['amount']);
            }
        }

        return '0.00';
    }
}
