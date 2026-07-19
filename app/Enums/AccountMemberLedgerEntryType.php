<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum AccountMemberLedgerEntryType: string
{
    use EnumToArray;

    case IncomeCustody = 'income_custody';
    case AccountFundExpense = 'account_fund_expense';
    case ExpensePaid = 'expense_paid';
    case ExpenseShare = 'expense_share';
    case InternalTransfer = 'internal_transfer';
    case SettlementTransfer = 'settlement_transfer';
    case ManualAdjustment = 'manual_adjustment';
    case LegacySettlement = 'legacy_settlement';
}
