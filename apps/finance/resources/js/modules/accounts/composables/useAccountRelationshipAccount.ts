import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed, type Ref } from 'vue';

import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';

import { accountQueryKeys } from '../queries/accountQueries';
import { createAccountsRepository } from '../repositories/accountsRepository';
import type { Account, AccountMember } from '../types';

const accountsRepository = createAccountsRepository();

export function useAccountRelationshipAccount(accountId: Ref<string>) {
  const queryClient = useQueryClient();
  const accountQuery = useQuery({
    queryKey: computed(() => accountQueryKeys.detail(accountId.value)),
    queryFn: () => {
      if (!accountId.value) {
        throw new Error('La cuenta solicitada no es válida.');
      }

      return accountsRepository.getById(accountId.value);
    },
    enabled: computed(() => Boolean(accountId.value)),
  });

  const account = computed(() => accountQuery.data.value ?? null);
  const isLoadingAccount = computed(() => accountQuery.isLoading.value);
  const loadError = computed(() => {
    if (!accountId.value) {
      return 'La cuenta solicitada no es válida.';
    }

    return accountQuery.error.value
      ? resolveApiErrorMessage(accountQuery.error.value, 'No fue posible cargar la cuenta.')
      : null;
  });

  async function reloadAccount(): Promise<void> {
    await accountQuery.refetch();
  }

  function setAccountUsers(nextUsers: AccountMember[]): void {
    if (!account.value) {
      return;
    }

    queryClient.setQueryData<Account>(accountQueryKeys.detail(account.value.id), {
      ...account.value,
      users: [...nextUsers],
    });
  }

  return {
    account,
    isLoadingAccount,
    loadError,
    reloadAccount,
    setAccountUsers,
  };
}
