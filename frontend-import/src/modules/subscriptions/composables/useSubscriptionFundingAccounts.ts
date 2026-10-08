import { useQuery } from '@tanstack/vue-query';
import { computed } from 'vue';

import { accountQueryKeys } from '@/modules/accounts/queries/accountQueries';
import { createAccountsRepository } from '@/modules/accounts/repositories/accountsRepository';

type FundingAccountOption = {
  value: string;
  label: string;
  description?: string;
};

const accountsRepository = createAccountsRepository();

export function useSubscriptionFundingAccounts() {
  const fundingAccountsQuery = useQuery({
    queryKey: accountQueryKeys.list(undefined),
    queryFn: () => accountsRepository.list(),
  });

  const fundingAccountOptions = computed<FundingAccountOption[]>(() =>
    (fundingAccountsQuery.data.value?.items ?? []).map((account) => ({
        value: account.id,
        label: account.name,
        description: account.description,
      })),
  );

  return {
    fundingAccountOptions,
    loadFundingAccounts: fundingAccountsQuery.refetch,
  };
}
