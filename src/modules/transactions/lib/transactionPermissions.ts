import type { TransactionStatus } from '@/modules/transactions/types';

type TransactionPermissionTarget = {
  creatorId: string | null;
};

type CompletableTransactionTarget = TransactionPermissionTarget & {
  status: TransactionStatus | null;
};

export function canManageTransaction(
  transaction: TransactionPermissionTarget,
  currentUserId: string | null,
): boolean {
  return Boolean(currentUserId && transaction.creatorId === currentUserId);
}

export function canCompleteTransaction(
  transaction: CompletableTransactionTarget,
  currentUserId: string | null,
): boolean {
  return canManageTransaction(transaction, currentUserId) && transaction.status === 'pending';
}
