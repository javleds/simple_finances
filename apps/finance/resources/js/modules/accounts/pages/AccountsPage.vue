<script setup lang="ts">
import Message from 'primevue/message';
import { computed } from 'vue';

import AccountDeleteModal from '@/modules/accounts/components/AccountDeleteModal.vue';
import AccountFiltersModal from '@/modules/accounts/components/AccountFiltersModal.vue';
import AccountFormModal from '@/modules/accounts/components/AccountFormModal.vue';
import AccountsList from '@/modules/accounts/components/AccountsList.vue';
import AccountsToolbar from '@/modules/accounts/components/AccountsToolbar.vue';
import { useAccountsCrud } from '@/modules/accounts/composables/useAccountsCrud';
import { useAccountFilters } from '@/modules/accounts/composables/useAccountFilters';
import { useAccountListLoader } from '@/modules/accounts/composables/useAccountListLoader';
import { useAccountModalActions } from '@/modules/accounts/composables/useAccountModalActions';
import { useAccountModals } from '@/modules/accounts/composables/useAccountModals';
import { canLeaveAccount } from '@/modules/accounts/lib/accountPermissions';
import type { AccountWritePayload } from '@/modules/accounts/types';
import { getStoredAuthSession } from '@/modules/auth/lib/authSession';
import { AppListState, AppLoadMoreFooter } from '@/modules/shared/components';

const statusOptions = ['Activo', 'Inactivo'] as const;
const kindOptions = [
    { value: 'credit', label: 'Crédito' },
    { value: 'debit', label: 'Débito' },
] as const;
const surfaceOptions = [
    { value: 'virtual', label: 'Virtual' },
    { value: 'physical', label: 'Física' },
] as const;
const currentUserId = computed(() => getStoredAuthSession()?.user.id ?? null);
const {
    activeFilters,
    applyFilters,
    searchTerm,
    selectedKinds,
    selectedStatuses,
    selectedSurfaces,
    toggleKind,
    toggleStatus,
    toggleSurface,
} = useAccountFilters();

const {
    accounts,
    hasAccounts,
    hasMoreAccounts,
    hasReachedEnd,
    isLoading,
    isLoadingMore,
    isSaving,
    isDeleting,
    loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadAccounts,
    loadMoreAccounts,
    createAccount,
    updateAccount,
    deleteAccount,
    leaveAccount,
} = useAccountsCrud();

const {
    closeCreateAccount,
    closeDeleteAccount,
    closeEditAccount,
    closeFilters,
    createFormState,
    editAccountInitialValues,
    editFormState,
    handleCreateFormStateChange,
    handleEditFormStateChange,
    isCreateAccountOpen,
    isDeleteAccountOpen,
    isEditAccountOpen,
    isFiltersOpen,
    openCreateAccount,
    openDeleteAccount,
    openEditAccount,
    openFilters,
    selectedAccount,
    selectedAccountId,
} = useAccountModals({
    accounts,
    clearDeleteError,
    clearSaveError,
});

const { createAccountActions, deleteAccountActions, editAccountActions } = useAccountModalActions({
    createFormState,
    currentUserId,
    editFormState,
    isDeleting,
    isSaving,
    selectedAccount,
});

const deleteModalMode = computed(() =>
    canLeaveAccount(selectedAccount.value, currentUserId.value) ? 'leave' : 'delete',
);

const { handleLoadMoreRetry, infiniteStatusLabel, loadMoreSentinel, reloadAccounts } =
    useAccountListLoader({
        activeFilters,
        hasMoreAccounts,
        hasReachedEnd,
        isLoading,
        isLoadingMore,
        loadAccounts,
        loadMoreAccounts,
    });

const filterChips = computed(() => [
    ...selectedStatuses.value.map((value) => ({
        key: `status-${value}`,
        label: value === 'Activo' ? 'Activas' : 'Inactivas',
        remove: () => toggleStatus(value),
    })),
    ...selectedKinds.value.map((value) => ({
        key: `kind-${value}`,
        label: kindOptions.find((option) => option.value === value)?.label ?? value,
        remove: () => toggleKind(value),
    })),
    ...selectedSurfaces.value.map((value) => ({
        key: `surface-${value}`,
        label: surfaceOptions.find((option) => option.value === value)?.label ?? value,
        remove: () => toggleSurface(value),
    })),
]);

async function handleCreateAccountSubmit(payload: AccountWritePayload): Promise<void> {
    if (isSaving.value) return;
    const wasCreated = await createAccount(payload);

    if (wasCreated) {
        closeCreateAccount();
    }
}

async function handleEditAccountSubmit(payload: AccountWritePayload): Promise<void> {
    if (isSaving.value) return;
    if (!selectedAccountId.value) {
        return;
    }

    const wasUpdated = await updateAccount(selectedAccountId.value, payload);

    if (wasUpdated) {
        closeEditAccount();
    }
}

async function confirmDeleteAccount(): Promise<void> {
    if (!selectedAccount.value) {
        return;
    }

    const wasDeleted =
        deleteModalMode.value === 'leave' && currentUserId.value
            ? await leaveAccount(selectedAccount.value.id, currentUserId.value)
            : await deleteAccount(selectedAccount.value.id);

    if (wasDeleted) {
        closeDeleteAccount();
    }
}
</script>

<template>
    <div class="space-y-5">
        <AccountsToolbar
            v-model:search-term="searchTerm"
            :filter-chips="filterChips"
            :is-filters-open="isFiltersOpen"
            @create="openCreateAccount"
            @open-filters="openFilters"
        />

        <Message v-if="loadError && hasAccounts" severity="error">{{ loadError }}</Message>

        <AppListState
            :error="loadError"
            :has-items="hasAccounts"
            :is-loading="isLoading"
            loading-label="Cargando cuentas..."
            @retry="reloadAccounts"
        >
            <AccountsList
                :accounts="accounts"
                :current-user-id="currentUserId"
                @delete="openDeleteAccount"
                @edit="openEditAccount"
                @leave="openDeleteAccount"
            >
                <template #footer>
                    <AppLoadMoreFooter
                        :label="infiniteStatusLabel()"
                        :show-retry="Boolean(loadError && hasAccounts)"
                        @retry="handleLoadMoreRetry"
                    />

                    <div
                        v-if="hasMoreAccounts || isLoadingMore || hasReachedEnd"
                        ref="loadMoreSentinel"
                        class="h-1 w-full"
                        aria-hidden="true"
                    />
                </template>
            </AccountsList>
        </AppListState>

        <AccountFiltersModal
            :open="isFiltersOpen"
            :kind-options="kindOptions"
            :selected-kinds="selectedKinds"
            :selected-statuses="selectedStatuses"
            :selected-surfaces="selectedSurfaces"
            :status-options="statusOptions"
            :surface-options="surfaceOptions"
            @apply="applyFilters"
            @close="closeFilters"
        />

        <AccountFormModal
            :open="isCreateAccountOpen"
            :actions="createAccountActions"
            form-id="account-form"
            :server-error="saveError"
            title="Nueva cuenta"
            @close="closeCreateAccount"
            @state-change="handleCreateFormStateChange"
            @submit="handleCreateAccountSubmit"
        />

        <AccountFormModal
            :open="isEditAccountOpen"
            :actions="editAccountActions"
            form-id="edit-account-form"
            :initial-values="editAccountInitialValues"
            requires-initial-values
            :server-error="saveError"
            title="Editar cuenta"
            @close="closeEditAccount"
            @state-change="handleEditFormStateChange"
            @submit="handleEditAccountSubmit"
        />

        <AccountDeleteModal
            :open="isDeleteAccountOpen"
            :account="selectedAccount"
            :actions="deleteAccountActions"
            :delete-error="deleteError"
            :mode="deleteModalMode"
            @close="closeDeleteAccount"
            @confirm="confirmDeleteAccount"
        />
    </div>
</template>
