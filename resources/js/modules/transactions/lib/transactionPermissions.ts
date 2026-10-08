type TransactionPermissionTarget = {
  creatorId: string | null;
};

export function canManageTransaction(
  transaction: TransactionPermissionTarget,
  currentUserId: string | null,
): boolean {
  return Boolean(currentUserId && transaction.creatorId === currentUserId);
}
