<?php

namespace App\Http\Controllers\Api;

use App\Models\Account;
use App\Services\Accounts\BuildAccountLedgerTimeline;
use App\Services\Api\AuthorizeAccountAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountLedgerController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly BuildAccountLedgerTimeline $buildAccountLedgerTimeline,
    ) {}

    public function index(Account $account, Request $request): JsonResponse
    {
        $this->authorizeAccountAccess->ensureMember($account);

        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));
        $page = max(1, (int) $request->integer('page', 1));
        $rows = $this->buildAccountLedgerTimeline->execute($account);
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return $this->respond([
            'data' => $slice,
            'meta' => [
                'current_page' => $page,
                'from' => $slice->isEmpty() ? null : (($page - 1) * $perPage) + 1,
                'last_page' => max(1, (int) ceil($rows->count() / $perPage)),
                'per_page' => $perPage,
                'to' => $slice->isEmpty() ? null : (($page - 1) * $perPage) + $slice->count(),
                'total' => $rows->count(),
            ],
        ]);
    }
}
