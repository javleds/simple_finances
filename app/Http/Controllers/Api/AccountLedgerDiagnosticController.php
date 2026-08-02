<?php

namespace App\Http\Controllers\Api;

use App\Models\Account;
use App\Services\Accounts\BuildAccountLedgerDiagnostics;
use App\Services\Api\AuthorizeAccountAccess;
use Illuminate\Http\JsonResponse;

class AccountLedgerDiagnosticController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly BuildAccountLedgerDiagnostics $buildAccountLedgerDiagnostics,
    ) {}

    public function index(Account $account): JsonResponse
    {
        $this->authorizeAccountAccess->ensureMember($account);

        return $this->respond([
            'data' => $this->buildAccountLedgerDiagnostics->execute($account),
        ]);
    }
}
