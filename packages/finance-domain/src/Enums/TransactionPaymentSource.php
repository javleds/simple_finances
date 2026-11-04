<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Enums;

use FinSi\FinanceDomain\Traits\EnumToArray;

enum TransactionPaymentSource: string
{
    use EnumToArray;

    case AccountFund = 'account_fund';
    case MemberOutOfPocket = 'member_out_of_pocket';
}
