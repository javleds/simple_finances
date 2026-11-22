<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SubscriptionPaymentRequest;
use App\Services\Transaction\ProcessTransactionSideEffects;

use function auth;

use Carbon\Carbon;
use FinSi\FinanceDomain\Authorization\AuthorizeAccountAccess;
use FinSi\FinanceDomain\Authorization\AuthorizeUserOwnedResource;
use FinSi\FinanceDomain\Enums\Action;
use FinSi\FinanceDomain\Enums\PaymentStatus;
use FinSi\FinanceDomain\Enums\TransactionStatus;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Subscription;
use FinSi\FinanceDomain\Models\SubscriptionPayment;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Money\Decimal;
use FinSi\FinanceDomain\Queries\UserOwnedResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use function sprintf;

class SubscriptionPaymentController extends ApiController
{
    public function __construct(
        private readonly UserOwnedResources $userOwnedResources,
        private readonly AuthorizeAccountAccess $authorizeAccountAccess,
        private readonly AuthorizeUserOwnedResource $authorizeUserOwnedResource,
        private readonly ProcessTransactionSideEffects $processTransactionSideEffects,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated(
            $this->userOwnedResources->constrain(SubscriptionPayment::query(), (int) $request->user()->id)
            ->with('subscription')
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
            'scheduled_at'    => $request->date('scheduled_at'),
            'amount'          => Decimal::normalize($request->validated('amount') ?? '0.00'),
            'status'          => $request->input('status', PaymentStatus::Pending->value),
        ]);

        $this->registerPaymentTransaction($record, $request->integer('account_id'));
        $this->refreshSubscriptionDates($subscription);

        return $this->respondModel($record->fresh(), ['subscription'], 201);
    }

    public function show(SubscriptionPayment $subscriptionPayment): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($subscriptionPayment, (int) auth()->id());

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

        if (PaymentStatus::Paid !== $originalStatus && PaymentStatus::Paid === $subscriptionPayment->status) {
            $this->registerPaymentTransaction($subscriptionPayment, $request->integer('account_id'));
        }

        $this->refreshSubscriptionDates($subscriptionPayment->subscription);

        return $this->respondModel($subscriptionPayment->fresh(), ['subscription']);
    }

    public function delete(SubscriptionPayment $subscriptionPayment): JsonResponse
    {
        $this->authorizeUserOwnedResource->ensureOwned($subscriptionPayment, (int) auth()->id());

        $subscription = $subscriptionPayment->subscription;
        $subscriptionPayment->delete();
        $this->refreshSubscriptionDates($subscription);

        return $this->respondDeleted('Subscription payment deleted successfully.');
    }

    private function registerPaymentTransaction(SubscriptionPayment $payment, ?int $accountId): void
    {
        if (PaymentStatus::Paid !== $payment->status || null === $accountId) {
            return;
        }

        $account = Account::withoutGlobalScopes()->find($accountId);

        if (! $account instanceof Account) {
            return;
        }

        $this->authorizeAccountAccess->ensureMember($account, (int) auth()->id());

        $transaction = Transaction::create([
            'amount'       => $payment->amount,
            'type'         => TransactionType::Outcome,
            'status'       => TransactionStatus::Completed,
            'scheduled_at' => Carbon::now(),
            'concept'      => sprintf('Pago de subscripción "%s"', $payment->subscription->name),
            'account_id'   => $account->id,
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
