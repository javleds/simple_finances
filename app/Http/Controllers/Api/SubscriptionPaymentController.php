<?php

namespace App\Http\Controllers\Api;

use App\Enums\Action;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\Api\SubscriptionPaymentRequest;
use App\Models\Account;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Transaction;
use App\Services\Api\AuthorizeAccountAccess;
use App\Services\Api\AuthorizeUserOwnedResource;
use App\Services\Transaction\ProcessTransactionSideEffects;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPaymentController extends ApiController
{
    public function __construct(
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly AuthorizeUserOwnedResource $authorizeUserOwnedResource,
        private readonly ProcessTransactionSideEffects $processTransactionSideEffects,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            SubscriptionPayment::query()
            ->with('subscription')
            ->where('user_id', $request->user()->id)
            ->latest('scheduled_at'),
            $request,
            filterColumns: ['subscription_id', 'status'],
        );
    }

    public function store(SubscriptionPaymentRequest $request): JsonResponse
    {
        $subscription = Subscription::withoutGlobalScopes()->findOrFail($request->integer('subscription_id'));
        $this->authorizeUserOwnedResource->ensureOwned($subscription, $request->user()->id);

        $record = SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'scheduled_at' => $request->date('scheduled_at'),
            'amount' => $request->float('amount'),
            'status' => $request->input('status', PaymentStatus::Pending->value),
        ]);

        $this->registerPaymentTransaction($record, $request->integer('account_id'));
        $this->refreshSubscriptionDates($subscription);

        return $this->respondModel($record->fresh(), ['subscription'], 201);
    }

    public function show(SubscriptionPayment $subscriptionPayment): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($subscriptionPayment);

        return $this->respondModel($subscriptionPayment, ['subscription']);
    }

    public function update(SubscriptionPaymentRequest $request, SubscriptionPayment $subscriptionPayment): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($subscriptionPayment, $request->user()->id);

        $originalStatus = $subscriptionPayment->status;

        $subscriptionPayment->fill($request->safe()->only([
            'scheduled_at',
            'amount',
            'status',
        ]));
        $subscriptionPayment->save();

        if ($originalStatus !== PaymentStatus::Paid && $subscriptionPayment->status === PaymentStatus::Paid) {
            $this->registerPaymentTransaction($subscriptionPayment, $request->integer('account_id'));
        }

        $this->refreshSubscriptionDates($subscriptionPayment->subscription);

        return $this->respondModel($subscriptionPayment->fresh(), ['subscription']);
    }

    public function delete(SubscriptionPayment $subscriptionPayment): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($subscriptionPayment);

        $subscription = $subscriptionPayment->subscription;
        $subscriptionPayment->delete();
        $this->refreshSubscriptionDates($subscription);

        return $this->respondDeleted('Subscription payment deleted successfully.');
    }

    private function registerPaymentTransaction(SubscriptionPayment $payment, ?int $accountId): void
    {
        if ($payment->status !== PaymentStatus::Paid || $accountId === null) {
            return;
        }

        $account = Account::withoutGlobalScopes()->find($accountId);

        if (! $account instanceof Account) {
            return;
        }

        $this->authorizeAccountAccess->ensureMember($account);

        $transaction = Transaction::create([
            'amount' => $payment->amount,
            'type' => TransactionType::Outcome,
            'status' => TransactionStatus::Completed,
            'scheduled_at' => Carbon::now(),
            'concept' => sprintf('Pago de subscripción "%s"', $payment->subscription->name),
            'account_id' => $account->id,
        ]);

        $this->processTransactionSideEffects->execute($transaction, Action::Created);
    }

    private function refreshSubscriptionDates(Subscription $subscription): void
    {
        $subscription->next_payment_date = $subscription->payments()
            ->where('status', PaymentStatus::Pending)
            ->orderBy('scheduled_at')
            ->first()?->scheduled_at;
        $subscription->previous_payment_date = $subscription->payments()
            ->where('status', PaymentStatus::Paid)
            ->orderByDesc('scheduled_at')
            ->first()?->scheduled_at;
        $subscription->save();
    }
}
