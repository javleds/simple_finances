<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum TransactionPaymentSource: string
{
    use EnumToArray;

    case AccountFund = 'account_fund';
    case MemberOutOfPocket = 'member_out_of_pocket';
}
