<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Enums;

enum SharedTransactionNotificationBatchStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
}
