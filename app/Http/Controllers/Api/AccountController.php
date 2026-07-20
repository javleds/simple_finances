<?php

namespace App\Http\Controllers\Api;

use App\Dto\AccountDto;
use App\Dto\ApiIndexCriteriaDto;
use App\Handlers\Accounts\AccountCreator;
use App\Handlers\Accounts\AccountEditor;
use App\Http\Requests\Api\AccountRequest;
use App\Models\Account;
use App\Services\Accounts\BuildAccountMemberSummary;
use App\Services\Accounts\RecalculateAccountBalance;
use App\Services\Accounts\VisibleAccountsForUser;
use App\Services\Api\ModelIndexCriteria;
use App\Services\Api\AuthorizeAccountAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly RecalculateAccountBalance $recalculateAccountBalance,
    ) {}

    public function index(
        Request $request,
        VisibleAccountsForUser $visibleAccountsForUser,
        BuildAccountMemberSummary $buildAccountMemberSummary,
        ModelIndexCriteria $modelIndexCriteria,
    ): JsonResponse
    {
        $query = $request->query->has('deleted_at')
            ? $visibleAccountsForUser->queryIncludingDeleted($request->user()->id)
            : $visibleAccountsForUser->query($request->user()->id);
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));
        $paginator = $modelIndexCriteria
            ->apply(
                $query
                    ->with(['users', 'feedAccount'])
                    ->orderBy('name'),
                new ApiIndexCriteriaDto(
                    request: $request,
                    filterColumns: ['credit_card', 'virtual', 'feed_account_id', 'user_id'],
                    nullableBooleanFilters: ['deleted_at' => 'deleted_at'],
                    searchColumns: ['name'],
                ),
            )
            ->paginate($perPage)
            ->withQueryString();

        collect($paginator->items())->each(function (Account $account) use ($buildAccountMemberSummary): void {
            foreach ($buildAccountMemberSummary->execute($account) as $key => $value) {
                $account->setAttribute($key, $value);
            }
        });

        return $this->respond([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(AccountRequest $request, AccountCreator $accountCreator): JsonResponse
    {
        $this->ensureFeedAccountAccess($request);

        $account = $accountCreator->execute(
            AccountDto::fromFormArray($request->validated())
        );

        $this->syncCreditCardDate($account, $request);

        return $this->respondModel($account->fresh(), ['users', 'feedAccount'], 201);
    }

    public function show(
        Account $account,
        BuildAccountMemberSummary $buildAccountMemberSummary,
    ): JsonResponse
    {
        $this->authorizeAccountAccess->ensureMember($account);
        foreach ($buildAccountMemberSummary->execute($account) as $key => $value) {
            $account->setAttribute($key, $value);
        }

        return $this->respondModel($account, ['users', 'feedAccount', 'financialGoals', 'invites']);
    }

    public function update(AccountRequest $request, Account $account, AccountEditor $accountEditor): JsonResponse
    {
        $this->authorizeAccountAccess->ensureOwner($account, $request->user()->id);
        $this->ensureFeedAccountAccess($request);

        $account = $accountEditor->execute(
            $account,
            AccountDto::fromFormArray($request->validated())
        );

        $this->syncCreditCardDate($account, $request);

        return $this->respondModel($account->fresh(), ['users', 'feedAccount']);
    }

    public function delete(Account $account): JsonResponse
    {
        $this->authorizeAccountAccess->ensureOwner($account);

        $account->delete();

        return $this->respondDeleted('Account deleted successfully.');
    }

    private function syncCreditCardDate(Account $account, AccountRequest $request): void
    {
        if (! $request->boolean('credit_card')) {
            $account->next_cutoff_date = null;
            $account->save();

            return;
        }

        $cutoffDay = (int) $request->integer('cutoff_day');
        $today = CarbonImmutable::now();

        $account->next_cutoff_date = $today->day < $cutoffDay
            ? $today->setDay($cutoffDay)->addMonth()->endOfDay()
            : $today->setDay($cutoffDay)->endOfDay();
        $account->save();
        $this->recalculateAccountBalance->execute($account);
    }

    private function ensureFeedAccountAccess(AccountRequest $request): void
    {
        $feedAccountId = $request->integer('feed_account_id');

        if ($feedAccountId === 0) {
            return;
        }

        $feedAccount = Account::withoutGlobalScopes()->findOrFail($feedAccountId);
        $this->authorizeAccountAccess->ensureMember($feedAccount, $request->user()->id);
    }
}
