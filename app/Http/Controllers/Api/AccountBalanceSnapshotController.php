<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AccountBalanceSnapshotRequest;
use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use App\Services\Api\AuthorizeAccountAccess;
use App\Services\VirtualAccounts\CaptureVirtualAccountBalanceSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountBalanceSnapshotController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly CaptureVirtualAccountBalanceSnapshot $captureSnapshot,
    ) {}

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
            observedBalance: (float) $request->validated('observed_balance'),
            observedAt: $request->validated('observed_at'),
            notes: $request->input('notes'),
        );

        return $this->respondModel($snapshot, ['adjustmentTransaction'], 201, [
            'account' => [
                'id' => $account->id,
                'balance' => (float) $account->fresh()->balance,
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
            observedBalance: (float) $request->validated('observed_balance'),
            observedAt: $request->validated('observed_at'),
            notes: $request->input('notes'),
        );

        return $this->respondModel($snapshot, ['adjustmentTransaction'], meta: [
            'account' => [
                'id' => $account->id,
                'balance' => (float) $account->fresh()->balance,
            ],
        ]);
    }

    public function delete(Account $account, AccountBalanceSnapshot $snapshot): JsonResponse
    {
        $this->ensureAccountSnapshot($account, $snapshot);

        $this->captureSnapshot->delete($snapshot);

        return $this->respondDeleted('Account balance snapshot deleted successfully.', [
            'account' => [
                'id' => $account->id,
                'balance' => (float) $account->fresh()->balance,
            ],
        ]);
    }

    private function ensureAccountMember(Account $account): void
    {
        $this->authorizeAccountAccess->ensureMember($account);
    }

    private function ensureAccountSnapshot(Account $account, AccountBalanceSnapshot $snapshot): void
    {
        $this->ensureAccountMember($account);
        $this->authorizeAccountAccess->ensureBelongsToAccount($account, $snapshot->account_id);
    }
}
