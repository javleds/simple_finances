<?php

namespace App\Http\Controllers\Api;

use FinSi\FinanceDomain\Models\Account;
use App\Services\Accounts\BuildAccountLedgerDiagnostics;
use FinSi\FinanceDomain\Authorization\AuthorizeAccountAccess;
use Illuminate\Http\JsonResponse;

class AccountLedgerDiagnosticController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly BuildAccountLedgerDiagnostics $buildAccountLedgerDiagnostics,
    ) {}

    public function index(Account $account): JsonResponse
    {
        $this->authorizeAccountAccess->ensureMember($account, (int) auth()->id());

        return $this->respond([
            'data' => $this->buildAccountLedgerDiagnostics->execute($account),
        ]);
    }
}
