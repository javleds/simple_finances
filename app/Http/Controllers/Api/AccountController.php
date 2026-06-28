<?php

namespace App\Http\Controllers\Api;

use App\Dto\AccountDto;
use App\Handlers\Accounts\AccountCreator;
use App\Handlers\Accounts\AccountEditor;
use App\Http\Requests\Api\AccountRequest;
use App\Models\Account;
use App\Services\Accounts\BuildPendingIncomeByUser;
use App\Services\Accounts\RecalculateAccountBalance;
use App\Services\Accounts\VisibleAccountsForUser;
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

    public function index(Request $request, VisibleAccountsForUser $visibleAccountsForUser): JsonResponse
    {
        return $this->respondPaginated(
            $visibleAccountsForUser->query($request->user()->id)
            ->with(['users', 'feedAccount'])
            ->orderBy('name'),
            $request,
            searchColumns: ['name'],
            filterColumns: ['credit_card', 'virtual', 'feed_account_id', 'user_id'],
        );
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

    public function show(Account $account, BuildPendingIncomeByUser $buildPendingIncomeByUser): JsonResponse
    {
        $this->authorizeAccountAccess->ensureMember($account);
        $account->setAttribute('pending_by_user', $buildPendingIncomeByUser->execute($account));

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
