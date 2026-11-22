<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Dto\AccountInviteDto;
use App\Http\Requests\Api\AccountInviteRequest;
use App\Services\AccountInvites\CreateAccountInvite;
use App\Services\AccountInvites\Respond;
use App\Services\AccountInvites\SendApiInvitationNotification;

use function auth;

use FinSi\FinanceDomain\Authorization\AuthorizeInviteAccess;
use FinSi\FinanceDomain\Authorization\AuthorizeUserOwnedResource;
use FinSi\FinanceDomain\Enums\InviteStatus;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use FinSi\FinanceDomain\Queries\VisibleInvitesForUser;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountInviteController extends ApiController
{
    public function __construct(
        private readonly AuthorizeInviteAccess $inviteAccess,
        private readonly VisibleInvitesForUser $visibleInvites,
        private readonly AuthorizeUserOwnedResource $authorizeUserOwnedResource,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            $this->visibleInvites->query($request->user())
            ->with([
                'account' => function (BelongsTo $query): void {
                    $query
                        ->withoutGlobalScopes()
                        ->select(['id', 'name']);
                },
                'user',
            ])
            ->latest(),
            $request,
            filterColumns: ['account_id', 'user_id', 'email', 'status'],
        );
    }

    public function store(AccountInviteRequest $request, CreateAccountInvite $createAccountInvite): JsonResponse
    {
        $account = Account::query()->findOrFail($request->integer('account_id'));
        $this->authorizeUserOwnedResource->ensureOwned($account, (int) $request->user()->id);

        $invite = $createAccountInvite->execute(new AccountInviteDto(
            account: $account,
            owner: $request->user(),
            email: $request->string('email')->toString(),
            percentage: Decimal::normalize($request->validated('percentage') ?? '0.00'),
        ));

        return $this->respondModel($invite, ['account', 'user'], 201);
    }

    public function show(AccountInvite $accountInvite): JsonResponse
    {
        $this->authorizeInviteAccess($accountInvite);

        return $this->respondModel($accountInvite, ['account', 'user']);
    }

    public function update(
        AccountInviteRequest $request,
        AccountInvite $accountInvite,
        Respond $respond,
        SendApiInvitationNotification $sendApiInvitationNotification,
    ): JsonResponse {
        $this->authorizeInviteAccess($accountInvite);

        if ($request->filled('status') && $request->user()->email === $accountInvite->email) {
            $invite = $respond->execute($accountInvite, InviteStatus::from($request->string('status')->toString()));

            return $this->respondModel($invite->fresh(), ['account', 'user']);
        }

        $this->authorizeUserOwnedResource->ensureOwned($accountInvite, (int) $request->user()->id);

        $accountInvite->fill($request->safe()->only([
            'email',
            'percentage',
            'status',
        ]));
        $accountInvite->save();

        if (InviteStatus::Pending === $accountInvite->status) {
            $sendApiInvitationNotification->execute($accountInvite);
        }

        return $this->respondModel($accountInvite->fresh(), ['account', 'user']);
    }

    public function delete(AccountInvite $accountInvite): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($accountInvite, (int) auth()->id());

        if ($accountInvite->isAccepted()) {
            $sharedUser = User::withoutGlobalScopes()
                ->where('email', $accountInvite->email)
                ->first();

            if ($sharedUser instanceof User) {
                $accountInvite->account()->withoutGlobalScopes()->first()?->users()->detach($sharedUser->id);
            }
        }

        $accountInvite->delete();

        return $this->respondDeleted('Account invite deleted successfully.');
    }

    private function authorizeInviteAccess(AccountInvite $accountInvite): void
    {
        $this->inviteAccess->ensureVisible($accountInvite, auth()->user());
    }
}
