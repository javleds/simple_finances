<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Dto\AccountInviteDto;
use App\Http\Requests\Api\AccountRelationInviteRequest;
use App\Services\AccountInvites\CreateAccountInvite;
use App\Services\AccountInvites\SendApiInvitationNotification;

use function auth;

use FinSi\FinanceDomain\Authorization\AuthorizeAccountAccess;
use FinSi\FinanceDomain\Enums\InviteStatus;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountRelationInviteController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
    ) {
    }

    public function index(Account $account, Request $request): JsonResponse
    {
        $this->authorizeAccountAccess->ensureMember($account, (int) auth()->id());

        $query = $account->invites()
            ->with(['account', 'user'])
            ->latest()
            ->getQuery();

        return $this->respondPaginated(
            $query,
            $request,
            searchColumns: ['email'],
            filterColumns: ['status'],
        );
    }

    public function store(Account $account, AccountRelationInviteRequest $request, CreateAccountInvite $createAccountInvite): JsonResponse
    {
        $this->ensureOwner($account);

        $invite = $createAccountInvite->execute(new AccountInviteDto(
            account: $account,
            owner: $request->user(),
            email: $request->string('email')->toString(),
            percentage: Decimal::normalize($request->validated('percentage') ?? '0.00'),
        ));

        return $this->respondModel($invite, ['account', 'user'], 201);
    }

    public function show(Account $account, AccountInvite $invite): JsonResponse
    {
        $this->authorizeAccountAccess->ensureMember($account, (int) auth()->id());
        $this->authorizeAccountAccess->ensureBelongsToAccount($account, $invite->account_id);

        return $this->respondModel($invite, ['account', 'user']);
    }

    public function update(
        Account $account,
        AccountInvite $invite,
        AccountRelationInviteRequest $request,
        SendApiInvitationNotification $sendApiInvitationNotification,
    ): JsonResponse {
        $this->ensureAccountInvite($account, $invite);

        $invite->fill($request->safe()->only([
            'email',
            'percentage',
            'status',
        ]));
        $invite->save();

        if (InviteStatus::Pending === $invite->status) {
            $sendApiInvitationNotification->execute($invite);
        }

        return $this->respondModel($invite->fresh(), ['account', 'user']);
    }

    public function delete(Account $account, AccountInvite $invite): JsonResponse
    {
        $this->ensureAccountInvite($account, $invite);

        if ($invite->isAccepted()) {
            $sharedUser = User::withoutGlobalScopes()
                ->where('email', $invite->email)
                ->first();

            if ($sharedUser instanceof User) {
                $account->users()->detach($sharedUser->id);
            }
        }

        $invite->delete();

        return $this->respondDeleted('Account invite relation deleted successfully.');
    }

    private function ensureOwner(Account $account): void
    {
        $this->authorizeAccountAccess->ensureOwner($account, (int) auth()->id());
    }

    private function ensureAccountInvite(Account $account, AccountInvite $invite): void
    {
        $this->ensureOwner($account);
        $this->authorizeAccountAccess->ensureBelongsToAccount($account, $invite->account_id);
    }
}
