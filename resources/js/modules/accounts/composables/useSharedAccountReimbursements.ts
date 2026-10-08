import { useQuery } from '@tanstack/vue-query';
import { computed } from 'vue';

import { accountQueryKeys } from '../queries/accountQueries';
import { createAccountsRepository } from '../repositories/accountsRepository';

const accountsRepository = createAccountsRepository();

export function useSharedAccountReimbursements() {
  const accountsQuery = useQuery({
    queryKey: accountQueryKeys.list(undefined, 100),
    queryFn: () => accountsRepository.list({ perPage: 100 }),
  });

  const accountsWithReimbursements = computed(
    () =>
      accountsQuery.data.value?.items.filter(
        (account) => account.pendingReimbursements.length > 0,
      ) ?? [],
  );

  return {
    accountsWithReimbursements,
    isLoadingReimbursements: computed(() => accountsQuery.isLoading.value),
    reloadReimbursements: accountsQuery.refetch,
  };
}
