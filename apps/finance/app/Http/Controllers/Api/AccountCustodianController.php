<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Dto\CreateAccountCustodianDto;
use App\Dto\RetireAccountCustodianDto;
use App\Http\Requests\Api\CreateAccountCustodianRequest;
use App\Http\Requests\Api\RetireAccountCustodianRequest;
use App\Services\Accounts\BuildAccountMemberSummary;
use App\Services\Accounts\CreateAccountCustodian;
use App\Services\Accounts\RetireAccountCustodian;

use function collect;

use FinSi\FinanceDomain\Authorization\AuthorizeAccountAccess;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountCustodianController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly BuildAccountMemberSummary $buildAccountMemberSummary,
    ) {
    }

    public function index(Account $account, Request $request): JsonResponse
    {
        $this->authorizeAccountAccess->ensureMember($account, (int) $request->user()->id);

        return $this->respond(['data' => $this->custodians($account)]);
    }

    public function store(Account $account, CreateAccountCustodianRequest $request, CreateAccountCustodian $createAccountCustodian): JsonResponse
    {
        $this->authorizeAccountAccess->ensureMember($account, (int) $request->user()->id);
        $custodian = $createAccountCustodian->execute($account, CreateAccountCustodianDto::fromArray($request->validated()), (int) $request->user()->id);

        return $this->respond(['data' => collect($this->custodians($account))->firstWhere('id', $custodian->id)], 201);
    }

    public function delete(Account $account, User $custodian, RetireAccountCustodianRequest $request, RetireAccountCustodian $retireAccountCustodian): JsonResponse
    {
        $this->authorizeAccountAccess->ensureMember($account, (int) $request->user()->id);
        $retireAccountCustodian->execute($account, $custodian, RetireAccountCustodianDto::fromArray($request->validated()));

        return $this->respondDeleted('Custodio eliminado y custodia redistribuida.');
    }

    private function custodians(Account $account): array
    {
        $summary = $this->buildAccountMemberSummary->execute($account);
        $custody = collect($summary['custody_by_user'])->keyBy('user_id');
        $settlements = collect($summary['settlements_by_user'])->keyBy('user_id');

        return $account->virtualCustodians()->whereNull('virtual_retired_at')->orderBy('name')->get()->map(static fn (User $user): array => [
            'id'                => $user->id,
            'name'              => $user->name,
            'custody_amount'    => (float) ($custody->get($user->id)['amount'] ?? 0),
            'settlement_amount' => (float) ($settlements->get($user->id)['amount'] ?? 0),
        ])->all();
    }
}
