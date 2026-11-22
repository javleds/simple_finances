<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use FinSi\FinanceDomain\Enums\TransactionPaymentSource;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Transaction;
use Illuminate\Validation\ValidationException;

class ValidateAccountFundPayment
{
    public function execute(Account $account, Transaction $transaction, ?Transaction $previous = null): void
    {
        if (true !== $transaction->uses_shared_account_rules
            || ! $transaction->uses_virtual_custodian_rules
            || TransactionType::Outcome !== $transaction->type
            || TransactionPaymentSource::AccountFund !== $transaction->payment_source) {
            return;
        }

        if (null !== $previous && $previous->account_id === $transaction->account_id
            && TransactionType::Outcome === $previous->type
            && TransactionPaymentSource::AccountFund === $previous->payment_source) {
            return;
        }

        if ($account->balance <= 0) {
            throw ValidationException::withMessages(['payment_source' => 'La cuenta no tiene saldo disponible. Registra el gasto como pagado de bolsillo.']);
        }
    }
}
