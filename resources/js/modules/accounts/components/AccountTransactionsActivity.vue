<script setup lang="ts">
import Message from 'primevue/message';
import AccountTransactionsList from '@/modules/accounts/components/AccountTransactionsList.vue';
import AccountTransactionsToolbar from '@/modules/accounts/components/AccountTransactionsToolbar.vue';
import { AppListState, AppLoadMoreFooter } from '@/modules/shared/components';
import type { Transaction } from '@/modules/transactions/types';

const props = defineProps<{
    activeFilterCount: number;
    filtersOpen: boolean;
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
        :active-filter-count="props.activeFilterCount"
        :filters-open="props.filtersOpen"
        @create="emit('create')"
        @open-filters="emit('openFilters')"
        @update:search-term="emit('update:searchTerm', $event)"
    />

    <Message v-if="props.loadError && props.hasTransactions" severity="error">{{
        props.loadError
    }}</Message>

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
