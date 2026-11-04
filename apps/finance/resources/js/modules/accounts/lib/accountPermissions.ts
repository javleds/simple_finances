import type { Account } from '@/modules/accounts/types';

export function canDeleteAccount(account: Account | null, currentUserId: string | null): boolean {
  return Boolean(account && currentUserId && account.ownerId === currentUserId);
}

export function canLeaveAccount(account: Account | null, currentUserId: string | null): boolean {
  return Boolean(account && currentUserId && account.ownerId !== currentUserId);
}
