<script setup lang="ts">
import Message from 'primevue/message';
import { computed, watch } from 'vue';
import { useRoute } from 'vue-router';

import AccountInvitationDeleteModal from '@/modules/accounts/components/AccountInvitationDeleteModal.vue';
import AccountInvitationFiltersModal from '@/modules/accounts/components/AccountInvitationFiltersModal.vue';
import AccountInvitationFormModal from '@/modules/accounts/components/AccountInvitationFormModal.vue';
import AccountInvitationsList from '@/modules/accounts/components/AccountInvitationsList.vue';
import AccountInvitationsToolbar from '@/modules/accounts/components/AccountInvitationsToolbar.vue';
import { useAccountInvitationActions } from '@/modules/accounts/composables/useAccountInvitationActions';
import { useAccountInvitationFilters } from '@/modules/accounts/composables/useAccountInvitationFilters';
import { useAccountInvitationModalActions } from '@/modules/accounts/composables/useAccountInvitationModalActions';
import { useAccountInvitationModals } from '@/modules/accounts/composables/useAccountInvitationModals';
import { useAccountInvitesCrud } from '@/modules/accounts/composables/useAccountInvitesCrud';
import type { AccountInviteStatus } from '@/modules/accounts/schemas/accountInviteSchemas';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import { AppListState, AppLoadMoreFooter } from '@/modules/shared/components';

const route = useRoute();
const { activeFilters, clearFilters, searchTerm, selectedStatuses, toggleStatus } =
    useAccountInvitationFilters();

const invitationStatusOptions = [
    { value: 'pending', label: 'Pendiente' },
    { value: 'accepted', label: 'Aceptada' },
    { value: 'declined', label: 'Declinada' },
] as const;
const defaultInvitesPerPage = 20;

const accountId = computed(() =>
    typeof route.params.accountId === 'string' ? route.params.accountId : '',
);

const {
    invites,
    hasInvites,
    hasMoreInvites,
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
    loadInvites,
    loadMoreInvites,
    createInvite,
    updateInvite,
    resendInvite,
    deleteInvite,
} = useAccountInvitesCrud();

const {
    closeCreateInvitation,
    closeDeleteInvitation,
    closeEditInvitation,
    closeFilters,
    createFormState,
    editFormState,
    handleCreateFormStateChange,
    handleEditFormStateChange,
    isCreateInvitationOpen,
    isDeleteInvitationOpen,
    isEditInvitationOpen,
    isFiltersOpen,
    openCreateInvitation,
    openDeleteInvitation,
    openEditInvitation,
    openFilters,
    selectedInvitation,
} = useAccountInvitationModals({
    clearDeleteError,
    clearSaveError,
    invitations: invites,
});

const { createInviteActions, deleteInviteActions, editInviteActions } =
    useAccountInvitationModalActions({
        createFormState,
        editFormState,
        isDeleting,
        isSaving,
        selectedInvitation,
    });

const invitationsPerPage = computed(() => {
    const rawValue =
        typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

    if (!Number.isInteger(rawValue) || rawValue <= 0) {
        return defaultInvitesPerPage;
    }

    return rawValue;
});

const { target: loadMoreSentinel } = useInfiniteScroll({
    enabled: computed(() => !isLoading.value && !isLoadingMore.value && hasMoreInvites.value),
    onIntersect: () => {
        void loadMoreInvites();
    },
});

const {
    confirmDeleteInvitation,
    handleCreateInvitationSubmit,
    handleEditInvitationSubmit,
    handleLoadMoreRetry,
    handleResendInvitation,
    infiniteStatusLabel,
    reloadInvitations,
} = useAccountInvitationActions({
    isSaving,
    accountId,
    activeFilters,
    closeCreateInvitation,
    closeDeleteInvitation,
    closeEditInvitation,
    createInvite,
    deleteInvite,
    hasReachedEnd,
    invitations: invites,
    invitationsPerPage,
    isLoadingMore,
    loadInvites,
    loadMoreInvites,
    resendInvite,
    selectedInvitation,
    updateInvite,
});

watch(
    [accountId, activeFilters, invitationsPerPage],
    ([nextAccountId, nextFilters, nextPerPage]) => {
        if (!nextAccountId) {
            return;
        }

        void loadInvites(nextAccountId, nextFilters, {
            reset: true,
            perPage: nextPerPage,
        });
    },
    { immediate: true },
);
</script>

<template>
    <section class="space-y-4">
        <AccountInvitationsToolbar
            v-model:search-term="searchTerm"
            @create="openCreateInvitation"
            @open-filters="openFilters"
        />

        <Message v-if="loadError && hasInvites" severity="error">{{ loadError }}</Message>

        <Message
            v-if="saveError && hasInvites && !isCreateInvitationOpen && !isEditInvitationOpen"
            severity="error"
            >{{ saveError }}</Message
        >

        <AppListState
            :error="loadError"
            :has-items="hasInvites"
            :is-loading="isLoading"
            loading-label="Cargando invitaciones..."
            @retry="reloadInvitations"
        >
            <AccountInvitationsList
                :invitations="invites"
                @delete="openDeleteInvitation"
                @edit="openEditInvitation"
                @resend="handleResendInvitation"
            >
                <template #footer>
                    <div ref="loadMoreSentinel">
                        <AppLoadMoreFooter
                            :label="infiniteStatusLabel()"
                            :show-retry="Boolean(loadError && hasInvites)"
                            @retry="handleLoadMoreRetry"
                        />
                    </div>
                </template>
            </AccountInvitationsList>
        </AppListState>

        <AccountInvitationFiltersModal
            :open="isFiltersOpen"
            :options="invitationStatusOptions"
            :selected-statuses="selectedStatuses"
            @clear="clearFilters"
            @close="closeFilters"
            @toggle-status="toggleStatus($event as AccountInviteStatus)"
        />

        <AccountInvitationFormModal
            :open="isCreateInvitationOpen"
            :account-id="accountId"
            :actions="createInviteActions"
            form-id="account-invitation-form"
            :server-error="saveError"
            title="Nueva invitación"
            @close="closeCreateInvitation"
            @state-change="handleCreateFormStateChange"
            @submit="handleCreateInvitationSubmit"
        />

        <AccountInvitationFormModal
            :open="isEditInvitationOpen"
            :account-id="accountId"
            :actions="editInviteActions"
            form-id="edit-account-invitation-form"
            :initial-values="selectedInvitation"
            :server-error="saveError"
            title="Editar invitación"
            @close="closeEditInvitation"
            @state-change="handleEditFormStateChange"
            @submit="handleEditInvitationSubmit"
        />

        <AccountInvitationDeleteModal
            :open="isDeleteInvitationOpen"
            :actions="deleteInviteActions"
            :delete-error="deleteError"
            :invitation="selectedInvitation"
            @close="closeDeleteInvitation"
            @confirm="confirmDeleteInvitation"
        />
    </section>
</template>
