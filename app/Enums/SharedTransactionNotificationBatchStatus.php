<?php

namespace App\Enums;

enum SharedTransactionNotificationBatchStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
}
