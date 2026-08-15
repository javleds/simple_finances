<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AccountMemberTransferRequest;
use App\Models\Account;
use App\Models\NotificationType;
use App\Models\User;
use App\Services\Accounts\BuildAccountLedgerTimeline;
use App\Services\Accounts\BuildAccountMemberSummary;
use App\Services\Accounts\RegisterAccountMemberTransfer;
use App\Services\Api\AuthorizeAccountAccess;
use App\Services\SharedTransactions\RegisterSharedTransactionNotificationAction;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;

class AccountMemberTransferController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly BuildAccountMemberSummary $buildAccountMemberSummary,
        private readonly BuildAccountLedgerTimeline $buildAccountLedgerTimeline,
    ) {}

    public function store(
        Account $account,
        AccountMemberTransferRequest $request,
        RegisterAccountMemberTransfer $registerAccountMemberTransfer,
        RegisterSharedTransactionNotificationAction $registerSharedTransactionNotificationAction,
    ): JsonResponse {
        $this->authorizeAccountAccess->ensureMember($account);
        $this->ensureAccountUser($account, $request->integer('from_user_id'));
        $this->ensureAccountUser($account, $request->integer('to_user_id'));

        $payload = $request->validated();
        $entries = $registerAccountMemberTransfer->execute($account, $payload);
        $this->registerReimbursementNotification(
            account: $account,
            payload: $payload,
            modifier: $request->user(),
            registerSharedTransactionNotificationAction: $registerSharedTransactionNotificationAction,
        );
        $freshAccount = $account->fresh();
        $meta = $this->buildAccountMemberSummary->execute($freshAccount);
        $meta['account'] = [
            'balance' => (float) $freshAccount->balance,
        ];
        $meta['ledger_rows'] = $this->buildAccountLedgerTimeline->execute($freshAccount)
            ->take(20)
            ->values()
            ->all();

        return $this->respond([
            'data' => $entries,
            'meta' => $meta,
        ], 201);
    }

    private function ensureAccountUser(Account $account, int $userId): void
    {
        $user = User::withoutGlobalScopes()->findOrFail($userId);
        $this->authorizeAccountAccess->ensureAccountUser($account, $user);
    }

    private function registerReimbursementNotification(
        Account $account,
        array $payload,
        User $modifier,
        RegisterSharedTransactionNotificationAction $registerSharedTransactionNotificationAction,
    ): void {
        if (config('notifications.shared_transactions.mode') !== 'grouped') {
            return;
        }

        $account->loadMissing('users');
        $description = $payload['description'] ?? 'Reembolso registrado';
        $occurredAt = isset($payload['occurred_at']) ? Carbon::parse($payload['occurred_at']) : now();

        foreach ($account->users as $user) {
            if ($user->id === $modifier->id) {
                continue;
            }

            if (! $user->canReceiveNotification(NotificationType::MOVEMENTS_NOTIFICATION)) {
                continue;
            }

            if (! $user->notificableAccounts()->get()->contains($account)) {
                continue;
            }

            $registerSharedTransactionNotificationAction->executeSettlement(
                recipient: $user,
                modifier: $modifier,
                account: $account,
                amount: (float) $payload['amount'],
                description: $description,
                occurredAt: $occurredAt,
            );
        }
    }
}
