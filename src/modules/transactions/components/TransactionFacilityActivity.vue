<script setup lang="ts">
import TransactionFacilityList from '@/modules/transactions/components/TransactionFacilityList.vue';
import TransactionFacilityToolbar from '@/modules/transactions/components/TransactionFacilityToolbar.vue';
import { AppListState, AppLoadMoreFooter, AppText } from '@/modules/shared/components';
import type { Transaction } from '@/modules/transactions/types';

const props = defineProps<{
  hasTransactions: boolean;
  infiniteStatusLabel: string;
  isLoading: boolean;
  loadError?: string | null;
  searchTerm: string;
  showLoadMoreRetry: boolean;
  transactions: Transaction[];
  validationError: string | null;
}>();

const emit = defineEmits<{
  'update:searchTerm': [value: string];
  loadMoreRetry: [];
  openFilters: [];
  retry: [];
}>();
</script>

<template>
  <TransactionFacilityToolbar
    :search-term="props.searchTerm"
    @open-filters="emit('openFilters')"
    @update:search-term="emit('update:searchTerm', $event)"
  />

  <section
    v-if="props.validationError"
    class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
  >
    <AppText class="text-(--app-color-danger)!">{{ props.validationError }}</AppText>
  </section>

  <section
    v-if="props.loadError && props.hasTransactions"
    class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
  >
    <AppText class="text-(--app-color-danger)!">{{ props.loadError }}</AppText>
  </section>

  <AppListState
    :error="props.validationError ? null : props.loadError"
    :has-items="props.hasTransactions"
    :is-loading="props.isLoading"
    loading-label="Cargando transacciones..."
    @retry="emit('retry')"
  >
    <TransactionFacilityList :transactions="props.transactions">
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
    </TransactionFacilityList>
  </AppListState>
</template>
