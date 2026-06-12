import { onMounted, ref } from 'vue';

import { createAccountsRepository } from '@/modules/accounts/repositories/accountsRepository';

type FundingAccountOption = {
  value: string;
  label: string;
  description?: string;
};

const accountsRepository = createAccountsRepository();

export function useSubscriptionFundingAccounts() {
  const fundingAccountOptions = ref<FundingAccountOption[]>([]);

  onMounted(() => {
    void loadFundingAccounts();
  });

  async function loadFundingAccounts(): Promise<void> {
    try {
      const response = await accountsRepository.list();
      fundingAccountOptions.value = response.items.map((account) => ({
        value: account.id,
        label: account.name,
        description: account.description,
      }));
    } catch {
      fundingAccountOptions.value = [];
    }
  }

  return {
    fundingAccountOptions,
    loadFundingAccounts,
  };
}
