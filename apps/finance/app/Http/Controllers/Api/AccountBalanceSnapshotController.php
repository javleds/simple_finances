<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AccountBalanceSnapshotRequest;
use App\Services\VirtualAccounts\CaptureVirtualAccountBalanceSnapshot;

use function auth;

use FinSi\FinanceDomain\Authorization\AuthorizeAccountAccess;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountBalanceSnapshot;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountBalanceSnapshotController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly CaptureVirtualAccountBalanceSnapshot $captureSnapshot,
    ) {
    }

    public function index(Account $account, Request $request): JsonResponse
    {
        $this->ensureAccountMember($account);

        $query = $account->balanceSnapshots()
            ->with('adjustmentTransaction')
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->getQuery();

        return $this->respondPaginated($query, $request);
    }

    public function store(Account $account, AccountBalanceSnapshotRequest $request): JsonResponse
    {
        $this->ensureAccountMember($account);

        $snapshot = $this->captureSnapshot->create(
            account: $account,
            userId: $request->user()->id,
            observedBalance: Decimal::normalize($request->validated('observed_balance')),
            observedAt: $request->validated('observed_at'),
            notes: $request->input('notes'),
        );

        return $this->respondModel($snapshot, ['adjustmentTransaction'], 201, [
            'account' => [
                'id'      => $account->id,
                'balance' => $account->fresh()->balance,
            ],
        ]);
    }

    public function update(
        Account $account,
        AccountBalanceSnapshot $snapshot,
        AccountBalanceSnapshotRequest $request,
    ): JsonResponse {
        $this->ensureAccountSnapshot($account, $snapshot);

        $snapshot = $this->captureSnapshot->update(
            snapshot: $snapshot,
            observedBalance: Decimal::normalize($request->validated('observed_balance')),
            observedAt: $request->validated('observed_at'),
            notes: $request->input('notes'),
        );

        return $this->respondModel($snapshot, ['adjustmentTransaction'], meta: [
            'account' => [
                'id'      => $account->id,
                'balance' => $account->fresh()->balance,
            ],
        ]);
    }

    public function delete(Account $account, AccountBalanceSnapshot $snapshot): JsonResponse
    {
        $this->ensureAccountSnapshot($account, $snapshot);

        $this->captureSnapshot->delete($snapshot);

        return $this->respondDeleted('Account balance snapshot deleted successfully.', [
            'account' => [
                'id'      => $account->id,
                'balance' => $account->fresh()->balance,
            ],
        ]);
    }

    private function ensureAccountMember(Account $account): void
    {
        $this->authorizeAccountAccess->ensureMember($account, (int) auth()->id());
    }

    private function ensureAccountSnapshot(Account $account, AccountBalanceSnapshot $snapshot): void
    {
        $this->ensureAccountMember($account);
        $this->authorizeAccountAccess->ensureBelongsToAccount($account, $snapshot->account_id);
    }
}
