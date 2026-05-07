<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AccountInviteController;
use App\Http\Controllers\Api\AccountUserNotificationController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\PasswordRecoveryController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\FinancialGoalController;
use App\Http\Controllers\Api\FixedIncomeController;
use App\Http\Controllers\Api\FixedOutcomeController;
use App\Http\Controllers\Api\NotificationSettingsController;
use App\Http\Controllers\Api\NotificationTypeController;
use App\Http\Controllers\Api\PartialFixedIncomeController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SharedTransactionNotificationBatchController;
use App\Http\Controllers\Api\SharedTransactionNotificationItemController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SubscriptionPaymentController;
use App\Http\Controllers\Api\TelegramVerificationCodeController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [RegisterController::class, 'store']);
    Route::post('login', [LoginController::class, 'store']);
    Route::post('password-recovery', [PasswordRecoveryController::class, 'store']);
    Route::put('password-reset', [PasswordResetController::class, 'update']);
    Route::get('email-verification/{id}/{hash}', [EmailVerificationController::class, 'show'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::middleware('api.auth')->group(function (): void {
        Route::delete('logout', [LogoutController::class, 'delete']);
        Route::post('email-verification-notification', [EmailVerificationNotificationController::class, 'store']);
    });
});

Route::middleware('api.auth')->group(function (): void {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);

    Route::get('notification-settings', [NotificationSettingsController::class, 'show']);
    Route::put('notification-settings', [NotificationSettingsController::class, 'update']);

    Route::get('accounts', [AccountController::class, 'index']);
    Route::post('accounts', [AccountController::class, 'store']);
    Route::get('accounts/{account}', [AccountController::class, 'show']);
    Route::put('accounts/{account}', [AccountController::class, 'update']);
    Route::delete('accounts/{account}', [AccountController::class, 'delete']);

    Route::get('account-invites', [AccountInviteController::class, 'index']);
    Route::post('account-invites', [AccountInviteController::class, 'store']);
    Route::get('account-invites/{accountInvite}', [AccountInviteController::class, 'show']);
    Route::put('account-invites/{accountInvite}', [AccountInviteController::class, 'update']);
    Route::delete('account-invites/{accountInvite}', [AccountInviteController::class, 'delete']);

    Route::get('account-user-notifications', [AccountUserNotificationController::class, 'index']);
    Route::post('account-user-notifications', [AccountUserNotificationController::class, 'store']);
    Route::get('account-user-notifications/{accountUserNotification}', [AccountUserNotificationController::class, 'show']);
    Route::put('account-user-notifications/{accountUserNotification}', [AccountUserNotificationController::class, 'update']);
    Route::delete('account-user-notifications/{accountUserNotification}', [AccountUserNotificationController::class, 'delete']);

    Route::get('financial-goals', [FinancialGoalController::class, 'index']);
    Route::post('financial-goals', [FinancialGoalController::class, 'store']);
    Route::get('financial-goals/{financialGoal}', [FinancialGoalController::class, 'show']);
    Route::put('financial-goals/{financialGoal}', [FinancialGoalController::class, 'update']);
    Route::delete('financial-goals/{financialGoal}', [FinancialGoalController::class, 'delete']);

    Route::get('fixed-incomes', [FixedIncomeController::class, 'index']);
    Route::post('fixed-incomes', [FixedIncomeController::class, 'store']);
    Route::get('fixed-incomes/{fixedIncome}', [FixedIncomeController::class, 'show']);
    Route::put('fixed-incomes/{fixedIncome}', [FixedIncomeController::class, 'update']);
    Route::delete('fixed-incomes/{fixedIncome}', [FixedIncomeController::class, 'delete']);

    Route::get('fixed-outcomes', [FixedOutcomeController::class, 'index']);
    Route::post('fixed-outcomes', [FixedOutcomeController::class, 'store']);
    Route::get('fixed-outcomes/{fixedOutcome}', [FixedOutcomeController::class, 'show']);
    Route::put('fixed-outcomes/{fixedOutcome}', [FixedOutcomeController::class, 'update']);
    Route::delete('fixed-outcomes/{fixedOutcome}', [FixedOutcomeController::class, 'delete']);

    Route::get('notification-types', [NotificationTypeController::class, 'index']);
    Route::post('notification-types', [NotificationTypeController::class, 'store']);
    Route::get('notification-types/{notificationType}', [NotificationTypeController::class, 'show']);
    Route::put('notification-types/{notificationType}', [NotificationTypeController::class, 'update']);
    Route::delete('notification-types/{notificationType}', [NotificationTypeController::class, 'delete']);

    Route::get('partial-fixed-incomes', [PartialFixedIncomeController::class, 'index']);
    Route::post('partial-fixed-incomes', [PartialFixedIncomeController::class, 'store']);
    Route::get('partial-fixed-incomes/{partialFixedIncome}', [PartialFixedIncomeController::class, 'show']);
    Route::put('partial-fixed-incomes/{partialFixedIncome}', [PartialFixedIncomeController::class, 'update']);
    Route::delete('partial-fixed-incomes/{partialFixedIncome}', [PartialFixedIncomeController::class, 'delete']);

    Route::get('shared-transaction-notification-batches', [SharedTransactionNotificationBatchController::class, 'index']);
    Route::post('shared-transaction-notification-batches', [SharedTransactionNotificationBatchController::class, 'store']);
    Route::get('shared-transaction-notification-batches/{batch}', [SharedTransactionNotificationBatchController::class, 'show']);
    Route::put('shared-transaction-notification-batches/{batch}', [SharedTransactionNotificationBatchController::class, 'update']);
    Route::delete('shared-transaction-notification-batches/{batch}', [SharedTransactionNotificationBatchController::class, 'delete']);

    Route::get('shared-transaction-notification-items', [SharedTransactionNotificationItemController::class, 'index']);
    Route::post('shared-transaction-notification-items', [SharedTransactionNotificationItemController::class, 'store']);
    Route::get('shared-transaction-notification-items/{item}', [SharedTransactionNotificationItemController::class, 'show']);
    Route::put('shared-transaction-notification-items/{item}', [SharedTransactionNotificationItemController::class, 'update']);
    Route::delete('shared-transaction-notification-items/{item}', [SharedTransactionNotificationItemController::class, 'delete']);

    Route::get('subscriptions', [SubscriptionController::class, 'index']);
    Route::post('subscriptions', [SubscriptionController::class, 'store']);
    Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show']);
    Route::put('subscriptions/{subscription}', [SubscriptionController::class, 'update']);
    Route::delete('subscriptions/{subscription}', [SubscriptionController::class, 'delete']);

    Route::get('subscription-payments', [SubscriptionPaymentController::class, 'index']);
    Route::post('subscription-payments', [SubscriptionPaymentController::class, 'store']);
    Route::get('subscription-payments/{subscriptionPayment}', [SubscriptionPaymentController::class, 'show']);
    Route::put('subscription-payments/{subscriptionPayment}', [SubscriptionPaymentController::class, 'update']);
    Route::delete('subscription-payments/{subscriptionPayment}', [SubscriptionPaymentController::class, 'delete']);

    Route::get('telegram-verification-codes', [TelegramVerificationCodeController::class, 'index']);
    Route::post('telegram-verification-codes', [TelegramVerificationCodeController::class, 'store']);
    Route::get('telegram-verification-codes/{telegramVerificationCode}', [TelegramVerificationCodeController::class, 'show']);
    Route::put('telegram-verification-codes/{telegramVerificationCode}', [TelegramVerificationCodeController::class, 'update']);
    Route::delete('telegram-verification-codes/{telegramVerificationCode}', [TelegramVerificationCodeController::class, 'delete']);

    Route::get('transactions', [TransactionController::class, 'index']);
    Route::post('transactions', [TransactionController::class, 'store']);
    Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
    Route::put('transactions/{transaction}', [TransactionController::class, 'update']);
    Route::delete('transactions/{transaction}', [TransactionController::class, 'delete']);
});
