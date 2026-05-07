<?php

namespace App\Http\Controllers\Api;

use App\Enums\InviteStatus;
use App\Events\AccountInviteCreated;
use App\Http\Requests\Api\AccountRelationInviteRequest;
use App\Models\Account;
use App\Models\AccountInvite;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountRelationInviteController extends ApiController
{
    public function index(Account $account, Request $request): JsonResponse
    {
        $this->ensureOwner($account);

        $query = $account->invites()
            ->with(['account', 'user'])
            ->latest()
            ->getQuery();

        return $this->respondPaginated(
            $query,
            $request,
        );
    }

    public function store(Account $account, AccountRelationInviteRequest $request): JsonResponse
    {
        $this->ensureOwner($account);

        $invite = $account->invites()->create($request->validated());

        return $this->respondModel($invite, ['account', 'user'], 201);
    }

    public function show(Account $account, AccountInvite $invite): JsonResponse
    {
        $this->ensureAccountInvite($account, $invite);

        return $this->respondModel($invite, ['account', 'user']);
    }

    public function update(Account $account, AccountInvite $invite, AccountRelationInviteRequest $request): JsonResponse
    {
        $this->ensureAccountInvite($account, $invite);

        $invite->fill($request->safe()->only([
            'email',
            'percentage',
            'status',
        ]));
        $invite->save();

        if ($invite->status === InviteStatus::Pending) {
            event(new AccountInviteCreated($invite));
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

        return $this->respond([
            'message' => 'Account invite relation deleted successfully.',
        ]);
    }

    private function ensureOwner(Account $account): void
    {
        abort_unless($account->user_id === auth()->id(), 403);
    }

    private function ensureAccountInvite(Account $account, AccountInvite $invite): void
    {
        $this->ensureOwner($account);
        abort_unless($invite->account_id === $account->id, 404);
    }
}
