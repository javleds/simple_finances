import type { AccountLedgerRow } from '@/modules/accounts/types';
import type { PaginatedCollection } from '@/modules/shared/lib/pagination';

export type TransactionType = 'income' | 'expense';
export type TransactionStatus = 'pending' | 'completed';
export type TransactionPaymentSource = 'account_fund' | 'member_out_of_pocket';
export type TransactionAttachmentFilters = {
  categoryIds?: string[];
  uncategorized?: boolean;
  hasAttachments?: boolean;
};
export type TransactionFilterCategory = { id: string; name: string; accountName: string | null };
export type TransactionListFilters = TransactionAttachmentFilters & {
  search?: string;
  type?: TransactionType[];
};

export type TransactionFacilityFilters = TransactionAttachmentFilters & {
  search?: string;
  startDate: string;
  endDate: string;
};

export type TransactionFacilitySummary = {
  incomeTotal: number;
  outcomeTotal: number;
  balance: number;
};

export type TransactionFacilityListResult = PaginatedCollection<Transaction> & {
  summary: TransactionFacilitySummary;
};

export type TransactionListResult = PaginatedCollection<Transaction> & {
  meta: TransactionMutationMeta;
};

export type Transaction = {
  id: string;
  accountId: string;
  accountName: string | null;
  attachmentsCount?: number;
  usesSharedAccountRules?: boolean | null;
  categoryName?: string | null;
  categoryId?: string | null;
  concept: string;
  amount: number;
  type: TransactionType;
  status: TransactionStatus | null;
  date: string;
  createdAt: string | null;
  creatorId: string | null;
  creatorName: string | null;
  paidByUserId: string | null;
  paidByUserName: string | null;
  custodianUserId: string | null;
  custodianUserName: string | null;
  paymentSource: TransactionPaymentSource | null;
  financialGoalId: string | null;
  financialGoalName: string | null;
  userPayments: Record<string, number>;
  currentUserPendingReimbursementAmount: number;
  currentUserReceivableReimbursementAmount: number;
};

export type TransactionFormValues = {
  type: TransactionType;
  status: 'completed';
  categoryId?: string | null;
  concept: string;
  amount: string;
  accountId: string | null;
  paidByUserId: string | null;
  custodianUserId: string | null;
  paymentSource: TransactionPaymentSource;
  splitBetweenUsers: boolean;
  date: string;
  financialGoalId: string | null;
  userPayments: Record<string, number>;
};

export type TransactionWritePayload = {
  attachments?: AttachmentDraft;
  type: TransactionType;
  status: 'completed';
  categoryId?: string | null;
  concept: string;
  amount: number;
  accountId: string;
  paidByUserId: string | null;
  custodianUserId: string | null;
  paymentSource: TransactionPaymentSource | null;
  splitBetweenUsers: boolean;
  date: string;
  financialGoalId: string | null;
  userPayments: Record<string, number>;
};

export type TransactionSubmitOptions = {
  keepOpen: boolean;
};

export type TransactionMutationMeta = {
  accountBalance: number | null;
  previousAccountBalance: number | null;
  custodyByUser: TransactionMemberAmount[] | null;
  settlementsByUser: TransactionMemberAmount[] | null;
  pendingReimbursements: TransactionPendingReimbursement[] | null;
  ledgerRows: AccountLedgerRow[] | null;
  subtransactionIds: string[];
};

export type TransactionMemberAmount = {
  userId: string;
  userName: string;
  amount: number;
};

export type TransactionPendingReimbursement = {
  fromUserId: string;
  fromUserName: string;
  toUserId: string;
  toUserName: string;
  amount: number;
  actionType: 'user_to_user' | 'custody_to_user' | 'user_to_account';
  items: TransactionPendingReimbursementItem[];
};

export type TransactionPendingReimbursementItem = {
  transactionId: string;
  concept: string;
  amount: number;
  occurredAt: string | null;
};

export type CreatedTransactionResult = {
  transaction: Transaction;
  transactions: Transaction[];
  meta: TransactionMutationMeta;
};

export type DeletedTransactionResult = {
  meta: TransactionMutationMeta;
};

export type AttachmentDraft = {
    files: Array<{ file: File; uploadId: string; uploaded: boolean }>;
    transactionId: string | null;
};

export type TransactionAttachment = {
    id: string;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    status: 'pending' | 'processing' | 'ready' | 'failed';
    error_message: string | null;
    can_manage: boolean;
    can_retry: boolean;
    created_at: string;
};

export type AttachmentConfig = {
    max_files: number;
    max_file_bytes: Record<'image' | 'pdf' | 'xml' | 'zip', number>;
    max_transaction_bytes: number;
    accepted_extensions: string[];
    poll_interval_ms: number;
};
