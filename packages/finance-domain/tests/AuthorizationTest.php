<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Tests;

use FinSi\FinanceDomain\Authorization\AuthorizeAccountAccess;
use FinSi\FinanceDomain\Authorization\AuthorizeInviteAccess;
use FinSi\FinanceDomain\Authorization\AuthorizeUserOwnedResource;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\Category;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Queries\VisibleAccountsForUser;
use FinSi\FinanceDomain\Queries\VisibleCategoriesForUser;
use FinSi\FinanceDomain\Queries\VisibleInvitesForUser;
use FinSi\FinanceDomain\Queries\VisibleTransactionsForUser;
use Illuminate\Contracts\Auth\Access\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AuthorizationTest extends TestCase
{
    public function testVisibilityAndAggregatesAreIsolatedBetweenActorsWithoutHttpAuthentication(): void
    {
        $owner = $this->user('owner');
        $member = $this->user('member');
        $stranger = $this->user('stranger');
        $shared = $this->account($owner, 'Shared');
        $shared->users()->attach($member);
        $private = $this->account($stranger, 'Private');
        $archived = $this->account($owner, 'Archived');
        $archived->delete();
        $this->transaction($shared, $owner, 25);
        $this->transaction($private, $stranger, 900);

        $accounts = $this->app->make(VisibleAccountsForUser::class);
        $transactions = $this->app->make(VisibleTransactionsForUser::class);

        self::assertSame([$shared->id], $accounts->query($member->id)->pluck('id')->all());
        self::assertSame([$private->id], $accounts->query($stranger->id)->pluck('id')->all());
        self::assertSame([$shared->id], $accounts->query($owner->id)->pluck('id')->all());
        self::assertEqualsCanonicalizing([$shared->id, $archived->id], $accounts->queryIncludingDeleted($owner->id)->pluck('id')->all());
        self::assertSame(25, $transactions->query($member->id)->sum('amount'));
        self::assertSame(900, $transactions->query($stranger->id)->sum('amount'));
        self::assertSame(0, $accounts->query(999)->count());
    }

    public function testProviderRegistersEffectiveReadPoliciesWithoutFinance(): void
    {
        $owner = $this->user('owner');
        $member = $this->user('member');
        $stranger = $this->user('stranger');
        $account = $this->account($owner, 'Shared');
        $account->users()->attach($member);
        $transaction = $this->transaction($account, $owner, 15);
        $gate = $this->app->make(Gate::class);

        self::assertTrue($gate->forUser($member)->allows('view', $account));
        self::assertTrue($gate->forUser($member)->allows('view', $transaction));
        self::assertFalse($gate->forUser($stranger)->allows('view', $account));
        self::assertFalse($gate->forUser($stranger)->allows('view', $transaction));
        self::assertFalse($gate->forUser($member)->allows('update', $transaction));
        self::assertTrue($gate->forUser($owner)->allows('update', $transaction));
    }

    public function testSharedMembershipDoesNotConferPrivateOwnership(): void
    {
        $owner = $this->user('owner');
        $member = $this->user('member');
        $account = $this->account($owner, 'Shared');
        $account->users()->attach($member);
        $this->app->make(AuthorizeAccountAccess::class)->ensureMember($account, $member->id);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);
        $this->app->make(AuthorizeUserOwnedResource::class)->ensureOwned($account, $member->id);
    }

    public function testAccountOwnerWithoutPivotIsNotImplicitlyAMember(): void
    {
        $owner = $this->user('owner');
        $account = $this->account($owner, 'Orphaned membership');
        $account->users()->detach($owner);
        $access = $this->app->make(AuthorizeAccountAccess::class);
        $access->ensureOwner($account, $owner->id);
        self::assertSame(0, $this->app->make(VisibleAccountsForUser::class)->query($owner->id)->count());

        $this->expectException(HttpException::class);
        $access->ensureMember($account, $owner->id);
    }

    public function testCategoriesPreservePersonalPrivacyAndSharedAccountScope(): void
    {
        $owner = $this->user('owner');
        $member = $this->user('member');
        $account = $this->account($owner, 'Shared categories');
        $account->forceFill(['uses_shared_categories' => true])->save();
        $account->users()->attach($member);
        $personal = new Category();
        $personal->forceFill(['user_id' => $owner->id, 'name' => 'Private'])->save();
        $shared = new Category();
        $shared->forceFill(['user_id' => $owner->id, 'account_id' => $account->id, 'name' => 'Shared'])->save();
        $query = $this->app->make(VisibleCategoriesForUser::class);

        self::assertSame([], $query->query($member->id)->pluck('id')->all());
        self::assertSame([$shared->id], $query->query($member->id, $account)->pluck('id')->all());
        self::assertSame([$personal->id], $query->query($owner->id)->pluck('id')->all());

        $stranger = $this->user('stranger');
        $this->expectException(HttpException::class);
        $query->query($stranger->id, $account)->count();
    }

    public function testInvitationInboxIsLimitedToRecipientAndDetailAllowsOwner(): void
    {
        $owner = $this->user('owner');
        $recipient = $this->user('recipient');
        $stranger = $this->user('stranger');
        $account = $this->account($owner, 'Invitations');
        $invite = new AccountInvite();
        $invite->forceFill(['user_id' => $owner->id, 'account_id' => $account->id, 'email' => $recipient->email])->save();
        $query = $this->app->make(VisibleInvitesForUser::class);
        $access = $this->app->make(AuthorizeInviteAccess::class);

        self::assertSame([$invite->id], $query->query($recipient)->pluck('id')->all());
        self::assertSame([], $query->query($owner)->pluck('id')->all());
        self::assertSame([], $query->query($stranger)->pluck('id')->all());
        $access->ensureVisible($invite, $owner);
        $access->ensureVisible($invite, $recipient);

        $this->expectException(HttpException::class);
        $access->ensureVisible($invite, $stranger);
    }

    public function testAttachmentReadingRequiresCurrentMembershipAndMatchingTransaction(): void
    {
        $owner = $this->user('owner');
        $member = $this->user('member');
        $account = $this->account($owner, 'Attachments');
        $account->users()->attach($member);
        $transaction = $this->transaction($account, $owner, 10);
        $attachment = new \FinSi\FinanceDomain\Models\TransactionAttachment();
        $attachment->forceFill(['transaction_id' => $transaction->id]);
        $access = $this->app->make(\FinSi\FinanceDomain\Authorization\AuthorizeAttachmentAccess::class);
        $access->execute($transaction, $member->id, false, $attachment);
        $account->users()->detach($member);

        try {
            $access->execute($transaction, $member->id, false, $attachment);
            self::fail('Removed members must lose attachment access.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }

        $attachment->transaction_id = $transaction->id + 1;

        try {
            $access->execute($transaction, $owner->id, false, $attachment);
            self::fail('Attachments from another transaction must not be exposed.');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->getStatusCode());
        }
    }

    public function testSharedProviderDoesNotDisableModelMassAssignmentGuards(): void
    {
        self::assertFalse(\Illuminate\Database\Eloquent\Model::isUnguarded());
        $this->expectException(\Illuminate\Database\Eloquent\MassAssignmentException::class);
        new Account(['user_id' => 123, 'name' => 'Untrusted input']);
    }

    public function testOwnedTransactionsRemainPrivateWhenTheAccountIsShared(): void
    {
        $owner = $this->user('owner');
        $member = $this->user('member');
        $account = $this->account($owner, 'Shared');
        $account->users()->attach($member);
        $own = $this->transaction($account, $owner, 10);
        $other = $this->transaction($account, $member, 20);
        $query = $this->app->make(\FinSi\FinanceDomain\Queries\OwnedTransactionsForUser::class);

        self::assertSame([$own->id], $query->query($owner->id)->pluck('id')->all());
        self::assertSame([$other->id], $query->query($member->id)->pluck('id')->all());
        $account->delete();
        self::assertSame(0, $query->query($owner->id)->count());
    }

    public function testPersonalMoneyRequiresAnActiveSoleMemberOwnedCurrentAccount(): void
    {
        $owner = $this->user('owner');
        $member = $this->user('member');
        $eligible = $this->account($owner, 'Personal');
        $shared = $this->account($owner, 'Shared');
        $shared->users()->attach($member);
        $foreign = $this->account($member, 'Foreign');
        $foreign->users()->attach($owner);
        $virtual = $this->account($owner, 'Virtual');
        $virtual->forceFill(['virtual' => true])->save();
        $credit = $this->account($owner, 'Credit');
        $credit->forceFill(['credit_card' => true])->save();
        $archived = $this->account($owner, 'Archived');
        $archived->delete();
        $orphan = $this->account($owner, 'No membership');
        $orphan->users()->detach($owner);
        $query = $this->app->make(\FinSi\FinanceDomain\Queries\EligiblePersonalMoneyAccounts::class);

        self::assertSame([$eligible->id], $query->execute($owner->id)->pluck('id')->all());
        self::assertSame([], $query->execute($member->id)->pluck('id')->all());
        $participants = $this->app->make(\FinSi\FinanceDomain\Authorization\AccountParticipants::class);
        self::assertEqualsCanonicalizing([$owner->id, $member->id], $participants->ids($shared));
        $shared->users()->detach($member);
        self::assertSame([$owner->id], $participants->ids($shared));
    }

    public function testNotificationItemsArePrivateToTheirBatchRecipient(): void
    {
        $first = $this->user('first');
        $second = $this->user('second');
        $firstBatch = new \FinSi\FinanceDomain\Models\SharedTransactionNotificationBatch();
        $firstBatch->forceFill(['user_id' => $first->id])->save();
        $secondBatch = new \FinSi\FinanceDomain\Models\SharedTransactionNotificationBatch();
        $secondBatch->forceFill(['user_id' => $second->id])->save();
        $firstItem = new \FinSi\FinanceDomain\Models\SharedTransactionNotificationItem();
        $firstItem->forceFill(['batch_id' => $firstBatch->id])->save();
        $secondItem = new \FinSi\FinanceDomain\Models\SharedTransactionNotificationItem();
        $secondItem->forceFill(['batch_id' => $secondBatch->id])->save();
        $query = $this->app->make(\FinSi\FinanceDomain\Queries\VisibleNotificationItemsForUser::class);

        self::assertSame([$firstItem->id], $query->query($first->id)->pluck('id')->all());
        self::assertSame([$secondItem->id], $query->query($second->id)->pluck('id')->all());
        self::assertSame(0, $query->query(999)->count());
    }

    public function testLegalAcceptanceRequiresCurrentDocumentsAndEveryConsentForTheActor(): void
    {
        $owner = $this->user('owner');
        $other = $this->user('other');
        $acceptance = new \FinSi\FinanceDomain\Models\LegalAcceptance();
        $acceptance->forceFill([
            'user_id'                 => $owner->id,
            'terms_hash'              => 'terms-current',
            'privacy_hash'            => 'privacy-current',
            'terms_accepted'          => true,
            'privacy_policy_accepted' => true,
            'financial_data_consent'  => true,
            'is_adult'                => true,
        ])->save();
        $access = $this->app->make(\FinSi\FinanceDomain\Authorization\HasCurrentLegalAcceptance::class);

        self::assertTrue($access->execute($owner->id, 'terms-current', 'privacy-current'));
        self::assertFalse($access->execute($other->id, 'terms-current', 'privacy-current'));
        self::assertFalse($access->execute($owner->id, 'terms-new', 'privacy-current'));
        self::assertFalse($access->execute($owner->id, 'terms-current', 'privacy-new'));

        foreach (['terms_accepted', 'privacy_policy_accepted', 'financial_data_consent', 'is_adult'] as $consent) {
            $acceptance->forceFill([$consent => false])->save();
            self::assertFalse($access->execute($owner->id, 'terms-current', 'privacy-current'));
            $acceptance->forceFill([$consent => true])->save();
        }
    }

    public function testArchivedTransactionDetailRetainsMembershipAccessWhileListsExcludeIt(): void
    {
        $owner = $this->user('owner');
        $member = $this->user('member');
        $account = $this->account($owner, 'Archived');
        $account->users()->attach($member);
        $transaction = $this->transaction($account, $owner, 10);
        $account->delete();
        $access = $this->app->make(\FinSi\FinanceDomain\Authorization\AuthorizeTransactionAccess::class);
        $query = $this->app->make(VisibleTransactionsForUser::class);

        self::assertTrue($access->isVisible($transaction, $member->id));
        self::assertSame(0, $query->query($member->id)->count());
        $account->users()->detach($member);
        self::assertFalse($access->isVisible($transaction, $member->id));
    }

    private function user(string $name): User
    {
        $user = new User();
        $user->forceFill(['name' => $name, 'email' => $name . '@example.com'])->save();

        return $user;
    }

    private function account(User $owner, string $name): Account
    {
        $account = new Account();
        $account->forceFill(['user_id' => $owner->id, 'name' => $name])->save();
        $account->users()->attach($owner);

        return $account;
    }

    private function transaction(Account $account, User $owner, int $amount): Transaction
    {
        $transaction = new Transaction();
        $transaction->forceFill(['account_id' => $account->id, 'user_id' => $owner->id, 'amount' => $amount, 'type' => 'income'])->save();

        return $transaction;
    }
}
