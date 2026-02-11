<?php

namespace App\Dto;

use App\Enums\Action;
use App\Models\Transaction;
use App\Models\User;

class SharedTransactionNotificationDto
{
    public function __construct(
        public User $recipient,
        public User $modifier,
        public Transaction $transaction,
        public Action $action,
    ) {}
}
