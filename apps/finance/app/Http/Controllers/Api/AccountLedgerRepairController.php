<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AccountLedgerRepairRequest;
use App\Services\Accounts\ApplyAccountLedgerRepair;

use function auth;

use FinSi\FinanceDomain\Authorization\AuthorizeAccountAccess;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountLedgerRepair;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Http\JsonResponse;

use function optional;
use function request;

class AccountLedgerRepairController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly ApplyAccountLedgerRepair $applyAccountLedgerRepair,
    ) {
    }

    public function store(Account $account, AccountLedgerRepairRequest $request): JsonResponse
    {
        $this->authorizeAccountAccess->ensureMember($account, (int) auth()->id());

        $repair = $this->applyAccountLedgerRepair->execute(
            account: $account,
            actor: $request->user(),
            payload: $request->validated(),
        );

        return $this->respond(['data' => $this->repairPayload($repair)], 201);
    }

    public function reverse(Account $account, AccountLedgerRepair $repair): JsonResponse
    {
        $this->authorizeAccountAccess->ensureMember($account, (int) auth()->id());

        $reversal = $this->applyAccountLedgerRepair->reverse(
            account: $account,
            actor: request()->user(),
            repair: $repair,
        );

        return $this->respond(['data' => $this->repairPayload($reversal)], 201);
    }

    private function repairPayload(AccountLedgerRepair $repair): array
    {
        $repair->loadMissing(['actor', 'targetTransaction']);
        $inputPayload = $repair->input_payload ?? [];

        return [
            'id'                         => (string) $repair->id,
            'status'                     => $repair->status,
            'issue_code'                 => $repair->issue_code,
            'repair_type'                => $repair->repair_type,
            'confidence'                 => $repair->confidence,
            'actor_user_id'              => (string) $repair->actor_user_id,
            'actor_user_name'            => $repair->actor?->name ?? 'Usuario no disponible',
            'target_transaction_id'      => $repair->target_transaction_id ? (string) $repair->target_transaction_id : null,
            'target_transaction_concept' => $repair->targetTransaction?->concept,
            'description'                => $inputPayload['description'] ?? $repair->issue_code,
            'amount'                     => Decimal::normalize($inputPayload['amount'] ?? '0.00'),
            'created_at'                 => optional($repair->created_at)->toIso8601String(),
            'can_reverse'                => 'applied' === $repair->status && null === $repair->reversed_by_repair_id,
            'preview'                    => $repair->preview_payload ?? [],
            'result'                     => $repair->result_payload ?? [],
        ];
    }
}
