import { computed, type ComputedRef } from 'vue';
import { useRoute } from 'vue-router';

import { getStoredAuthSession } from '@/modules/auth/lib/authSession';
import type { Account } from '@/modules/accounts/types';

export function useAccountTransactionsContext(account: ComputedRef<Account | undefined>) {
  const route = useRoute();
  const accountId = computed(() =>
    typeof route.params.accountId === 'string' ? route.params.accountId : '',
  );
  const accountUsers = computed(() => account.value?.users ?? []);
  const currentUserId = computed(() => getStoredAuthSession()?.user.id ?? null);
  const isSharedAccount = computed(() => accountUsers.value.length > 1);

  return {
    accountId,
    accountUsers,
    currentUserId,
    isSharedAccount,
  };
}
