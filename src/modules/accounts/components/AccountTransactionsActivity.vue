<script setup lang="ts">
import AccountTransactionsList from '@/modules/accounts/components/AccountTransactionsList.vue';
import AccountTransactionsToolbar from '@/modules/accounts/components/AccountTransactionsToolbar.vue';
import { AppListState, AppLoadMoreFooter, AppText } from '@/modules/shared/components';
import type { Transaction } from '@/modules/transactions/types';

const props = defineProps<{
  currentUserId: string | null;
  hasTransactions: boolean;
  infiniteStatusLabel: string;
  isLoading: boolean;
  loadError?: string | null;
  searchTerm: string;
  showLoadMoreRetry: boolean;
  transactions: Transaction[];
}>();

const emit = defineEmits<{
  'update:searchTerm': [value: string];
  complete: [transactionId: string];
  create: [];
  delete: [transactionId: string];
  edit: [transactionId: string];
  loadMoreRetry: [];
  openFilters: [];
  retry: [];
}>();
</script>

<template>
  <AccountTransactionsToolbar
    :search-term="props.searchTerm"
    @create="emit('create')"
    @open-filters="emit('openFilters')"
    @update:search-term="emit('update:searchTerm', $event)"
  />

  <section
    v-if="props.loadError && props.hasTransactions"
    class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
  >
    <AppText class="text-(--app-color-danger)!">{{ props.loadError }}</AppText>
  </section>

  <AppListState
    :error="props.loadError"
    :has-items="props.hasTransactions"
    :is-loading="props.isLoading"
    loading-label="Cargando transacciones..."
    @retry="emit('retry')"
  >
    <AccountTransactionsList
      :current-user-id="props.currentUserId"
      :transactions="props.transactions"
      @complete="emit('complete', $event)"
      @delete="emit('delete', $event)"
      @edit="emit('edit', $event)"
    >
      <template #footer>
        <div>
          <slot name="loadMoreSentinel" />
          <AppLoadMoreFooter
            :label="props.infiniteStatusLabel"
            :show-retry="props.showLoadMoreRetry"
            @retry="emit('loadMoreRetry')"
          />
        </div>
      </template>
    </AccountTransactionsList>
  </AppListState>
</template>
