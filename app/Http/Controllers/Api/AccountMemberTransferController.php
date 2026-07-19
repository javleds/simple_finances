<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AccountMemberTransferRequest;
use App\Models\Account;
use App\Models\User;
use App\Services\Accounts\BuildAccountMemberSummary;
use App\Services\Accounts\RegisterAccountMemberTransfer;
use App\Services\Api\AuthorizeAccountAccess;
use Illuminate\Http\JsonResponse;

class AccountMemberTransferController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly BuildAccountMemberSummary $buildAccountMemberSummary,
    ) {}

    public function store(
        Account $account,
        AccountMemberTransferRequest $request,
        RegisterAccountMemberTransfer $registerAccountMemberTransfer,
    ): JsonResponse {
        $this->authorizeAccountAccess->ensureMember($account);
        $this->ensureAccountUser($account, $request->integer('from_user_id'));
        $this->ensureAccountUser($account, $request->integer('to_user_id'));

        $entries = $registerAccountMemberTransfer->execute($account, $request->validated());

        return $this->respond([
            'data' => $entries,
            'meta' => $this->buildAccountMemberSummary->execute($account),
        ], 201);
    }

    private function ensureAccountUser(Account $account, int $userId): void
    {
        $user = User::withoutGlobalScopes()->findOrFail($userId);
        $this->authorizeAccountAccess->ensureAccountUser($account, $user);
    }
}
