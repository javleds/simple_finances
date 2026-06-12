<?php

namespace App\Http\Controllers\Api;

use App\Dto\AccountInviteDto;
use App\Enums\InviteStatus;
use App\Events\AccountInviteCreated;
use App\Http\Requests\Api\AccountInviteRequest;
use App\Models\Account;
use App\Models\AccountInvite;
use App\Models\User;
use App\Services\AccountInvites\CreateAccountInvite;
use App\Services\AccountInvites\Respond;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountInviteController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            AccountInvite::query()
            ->with(['account', 'user'])
            ->where('email', $request->user()->email)
            ->latest(),
            $request,
        );
    }

    public function store(AccountInviteRequest $request, CreateAccountInvite $createAccountInvite): JsonResponse
    {
        $account = Account::query()->findOrFail($request->integer('account_id'));
        abort_unless($account->user_id === $request->user()->id, 403);

        $invite = $createAccountInvite->execute(new AccountInviteDto(
            account: $account,
            owner: $request->user(),
            email: $request->string('email')->toString(),
            percentage: $request->float('percentage'),
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
    ): JsonResponse {
        $this->authorizeInviteAccess($accountInvite);

        if ($request->filled('status') && $request->user()->email === $accountInvite->email) {
            $invite = $respond->execute($accountInvite, InviteStatus::from($request->string('status')->toString()));

            return $this->respondModel($invite->fresh(), ['account', 'user']);
        }

        abort_unless($accountInvite->user_id === $request->user()->id, 403);

        $accountInvite->fill($request->safe()->only([
            'email',
            'percentage',
            'status',
        ]));
        $accountInvite->save();

        if ($accountInvite->status === InviteStatus::Pending) {
            event(new AccountInviteCreated($accountInvite));
        }

        return $this->respondModel($accountInvite->fresh(), ['account', 'user']);
    }

    public function delete(AccountInvite $accountInvite): JsonResponse
    {
        abort_unless($accountInvite->user_id === auth()->id(), 403);

        if ($accountInvite->isAccepted()) {
            $sharedUser = User::withoutGlobalScopes()
                ->where('email', $accountInvite->email)
                ->first();

            if ($sharedUser instanceof User) {
                $accountInvite->account()->withoutGlobalScopes()->first()?->users()->detach($sharedUser->id);
            }
        }

        $accountInvite->delete();

        return $this->respond([
            'message' => 'Account invite deleted successfully.',
        ]);
    }

    private function authorizeInviteAccess(AccountInvite $accountInvite): void
    {
        abort_unless(
            $accountInvite->user_id === auth()->id() || $accountInvite->email === auth()->user()->email,
            403,
        );
    }
}
