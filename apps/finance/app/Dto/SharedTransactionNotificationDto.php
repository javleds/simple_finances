<?php

namespace App\Dto;

use FinSi\FinanceDomain\Enums\Action;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;

class SharedTransactionNotificationDto
{
    public function __construct(
        public User $recipient,
        public User $modifier,
        public Transaction $transaction,
        public Action $action,
    ) {}
}
