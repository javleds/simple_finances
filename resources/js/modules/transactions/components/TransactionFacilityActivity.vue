<script setup lang="ts">
import Message from 'primevue/message';
import TransactionFacilityList from '@/modules/transactions/components/TransactionFacilityList.vue';
import TransactionFacilityToolbar from '@/modules/transactions/components/TransactionFacilityToolbar.vue';
import { AppListState, AppLoadMoreFooter } from '@/modules/shared/components';
import type { Transaction } from '@/modules/transactions/types';

import type { ActiveFilter } from '@/modules/shared/types/filters';

const props = defineProps<{
    filterChips: readonly ActiveFilter[];
    isFiltersOpen: boolean;
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
        :filter-chips="props.filterChips"
        :is-filters-open="props.isFiltersOpen"
        @open-filters="emit('openFilters')"
        @update:search-term="emit('update:searchTerm', $event)"
    />

    <Message v-if="props.validationError" severity="error">{{ props.validationError }}</Message>

    <Message v-if="props.loadError && props.hasTransactions" severity="error">{{
        props.loadError
    }}</Message>

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
