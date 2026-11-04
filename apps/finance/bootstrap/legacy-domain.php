<?php

declare(strict_types=1);

$legacyDomainClasses = [
    'App\\Enums\\Action'                                   => FinSi\FinanceDomain\Enums\Action::class,
    'App\\Models\\Account'                                 => FinSi\FinanceDomain\Models\Account::class,
    'App\\Models\\AccountBalanceSnapshot'                  => FinSi\FinanceDomain\Models\AccountBalanceSnapshot::class,
    'App\\Models\\AccountInvite'                           => FinSi\FinanceDomain\Models\AccountInvite::class,
    'App\\Models\\AccountLedgerRepair'                     => FinSi\FinanceDomain\Models\AccountLedgerRepair::class,
    'App\\Models\\AccountMemberLedgerEntry'                => FinSi\FinanceDomain\Models\AccountMemberLedgerEntry::class,
    'App\\Models\\AccountUserNotification'                 => FinSi\FinanceDomain\Models\AccountUserNotification::class,
    'App\\Models\\Category'                                => FinSi\FinanceDomain\Models\Category::class,
    'App\\Models\\FinancialGoal'                           => FinSi\FinanceDomain\Models\FinancialGoal::class,
    'App\\Models\\FixedIncome'                             => FinSi\FinanceDomain\Models\FixedIncome::class,
    'App\\Models\\FixedOutcome'                            => FinSi\FinanceDomain\Models\FixedOutcome::class,
    'App\\Models\\LegalAcceptance'                         => FinSi\FinanceDomain\Models\LegalAcceptance::class,
    'App\\Models\\NotificationSetup'                       => FinSi\FinanceDomain\Models\NotificationSetup::class,
    'App\\Models\\NotificationType'                        => FinSi\FinanceDomain\Models\NotificationType::class,
    'App\\Models\\PartialFixedIncome'                      => FinSi\FinanceDomain\Models\PartialFixedIncome::class,
    'App\\Models\\PersonalMoneyLink'                       => FinSi\FinanceDomain\Models\PersonalMoneyLink::class,
    'App\\Models\\PersonalMoneyReimbursement'              => FinSi\FinanceDomain\Models\PersonalMoneyReimbursement::class,
    'App\\Models\\PersonalMoneySetting'                    => FinSi\FinanceDomain\Models\PersonalMoneySetting::class,
    'App\\Models\\SharedTransactionNotificationBatch'      => FinSi\FinanceDomain\Models\SharedTransactionNotificationBatch::class,
    'App\\Models\\SharedTransactionNotificationItem'       => FinSi\FinanceDomain\Models\SharedTransactionNotificationItem::class,
    'App\\Models\\Subscription'                            => FinSi\FinanceDomain\Models\Subscription::class,
    'App\\Models\\SubscriptionPayment'                     => FinSi\FinanceDomain\Models\SubscriptionPayment::class,
    'App\\Models\\TelegramVerificationCode'                => FinSi\FinanceDomain\Models\TelegramVerificationCode::class,
    'App\\Models\\Transaction'                             => FinSi\FinanceDomain\Models\Transaction::class,
    'App\\Models\\TransactionAllocation'                   => FinSi\FinanceDomain\Models\TransactionAllocation::class,
    'App\\Models\\TransactionAttachment'                   => FinSi\FinanceDomain\Models\TransactionAttachment::class,
    'App\\Models\\User'                                    => FinSi\FinanceDomain\Models\User::class,
    'App\\Models\\WebhookReceipt'                          => FinSi\FinanceDomain\Models\WebhookReceipt::class,
    'App\\Models\\WhatsAppConnection'                      => FinSi\FinanceDomain\Models\WhatsAppConnection::class,
    'App\\Models\\WhatsAppVerificationCode'                => FinSi\FinanceDomain\Models\WhatsAppVerificationCode::class,
    'App\\Enums\\AccountMemberLedgerEntryType'             => FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType::class,
    'App\\Enums\\FinancialGoalStatus'                      => FinSi\FinanceDomain\Enums\FinancialGoalStatus::class,
    'App\\Enums\\FixedIncomeFrequency'                     => FinSi\FinanceDomain\Enums\FixedIncomeFrequency::class,
    'App\\Enums\\FixedOutcomeType'                         => FinSi\FinanceDomain\Enums\FixedOutcomeType::class,
    'App\\Enums\\Frequency'                                => FinSi\FinanceDomain\Enums\Frequency::class,
    'App\\Enums\\InviteStatus'                             => FinSi\FinanceDomain\Enums\InviteStatus::class,
    'App\\Enums\\PaymentStatus'                            => FinSi\FinanceDomain\Enums\PaymentStatus::class,
    'App\\Enums\\SharedTransactionNotificationAction'      => FinSi\FinanceDomain\Enums\SharedTransactionNotificationAction::class,
    'App\\Enums\\SharedTransactionNotificationBatchStatus' => FinSi\FinanceDomain\Enums\SharedTransactionNotificationBatchStatus::class,
    'App\\Enums\\TransactionPaymentSource'                 => FinSi\FinanceDomain\Enums\TransactionPaymentSource::class,
    'App\\Enums\\TransactionStatus'                        => FinSi\FinanceDomain\Enums\TransactionStatus::class,
    'App\\Enums\\TransactionType'                          => FinSi\FinanceDomain\Enums\TransactionType::class,
];

spl_autoload_register(static function (string $class) use ($legacyDomainClasses): void {
    if (isset($legacyDomainClasses[$class])) {
        class_alias($legacyDomainClasses[$class], $class);
    }
});
