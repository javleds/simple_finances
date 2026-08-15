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
    case SettlementCorrection = 'settlement_correction';
    case CustodyCorrection = 'custody_correction';
    case CustodyReimbursementDue = 'custody_reimbursement_due';
    case CustodyReimbursementPayment = 'custody_reimbursement_payment';
    case AccountDeficitShare = 'account_deficit_share';
    case AccountDeficitPayment = 'account_deficit_payment';
    case ManualAdjustment = 'manual_adjustment';
    case LegacySettlement = 'legacy_settlement';
}
