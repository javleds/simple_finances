<script setup lang="ts">
import {
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import AccountInvitationDeleteModal from '@/modules/accounts/components/AccountInvitationDeleteModal.vue';
import AccountInvitationFiltersModal from '@/modules/accounts/components/AccountInvitationFiltersModal.vue';
import AccountInvitationFormModal from '@/modules/accounts/components/AccountInvitationFormModal.vue';
import AccountInvitationsList from '@/modules/accounts/components/AccountInvitationsList.vue';
import AccountInvitationsToolbar from '@/modules/accounts/components/AccountInvitationsToolbar.vue';
import { useAccountInvitesCrud } from '@/modules/accounts/composables/useAccountInvitesCrud';
import type {
  AccountInviteStatus,
  AccountInviteWritePayload,
} from '@/modules/accounts/schemas/accountInviteSchemas';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import { areQueriesEqual, parseQueryValues } from '@/modules/shared/lib/queryParams';
import {
  AppButton,
  AppListState,
  AppLoadMoreFooter,
  AppText,
} from '@/modules/shared/components';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const route = useRoute();
const router = useRouter();
const searchTerm = ref('');
const isFiltersOpen = ref(false);
const isCreateInvitationOpen = ref(false);
const isEditInvitationOpen = ref(false);
const isDeleteInvitationOpen = ref(false);
const selectedStatuses = ref<AccountInviteStatus[]>([]);
const selectedInvitationId = ref<string | null>(null);
const createFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });
const editFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });

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
  deleteInvite,
} = useAccountInvitesCrud();

const invitationsPerPage = computed(() => {
  const rawValue = typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

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

const activeFilters = computed(() => ({
  search: searchTerm.value.trim() || undefined,
  status: selectedStatuses.value.length > 0 ? [...selectedStatuses.value] : undefined,
}));

const selectedInvitation = computed(() => {
  if (!selectedInvitationId.value) {
    return null;
  }

  return invites.value.find((invitation) => invitation.id === selectedInvitationId.value) ?? null;
});

const createInviteActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-invitation',
    label: isSaving.value ? 'Guardando...' : 'Crear invitación',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'account-invitation-form',
    disabled: !createFormState.value.canSubmit || isSaving.value,
  },
]);

const editInviteActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-edit-invitation',
    label: isSaving.value ? 'Guardando...' : 'Guardar cambios',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'edit-account-invitation-form',
    disabled: !editFormState.value.canSubmit || isSaving.value,
  },
]);

const deleteInviteActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'confirm-delete-invitation',
    label: isDeleting.value ? 'Eliminando...' : 'Eliminar invitación',
    tone: 'primary' as const,
    disabled: !selectedInvitation.value || isDeleting.value,
  },
]);

watch(
  () => route.query,
  (nextQuery) => {
    searchTerm.value = typeof nextQuery.search === 'string' ? nextQuery.search : '';
    selectedStatuses.value = parseQueryValues(nextQuery.status, isInvitationStatus);
  },
  { immediate: true },
);

watch(
  [searchTerm, selectedStatuses],
  () => {
    const nextQuery = {
      ...route.query,
      search: searchTerm.value.trim() || undefined,
      status: selectedStatuses.value.length > 0 ? selectedStatuses.value.join(',') : undefined,
    };

    if (areQueriesEqual(route.query, nextQuery)) {
      return;
    }

    void router.replace({ query: nextQuery });
  },
  { deep: true },
);

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

function openFilters(): void {
  isFiltersOpen.value = true;
}

function closeFilters(): void {
  isFiltersOpen.value = false;
}

function clearFilters(): void {
  selectedStatuses.value = [];
}

function toggleStatus(status: AccountInviteStatus): void {
  if (selectedStatuses.value.includes(status)) {
    selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
    return;
  }

  selectedStatuses.value = [...selectedStatuses.value, status];
}

function openCreateInvitation(): void {
  clearSaveError();
  createFormState.value = { canSubmit: false, isSubmitting: false };
  isCreateInvitationOpen.value = true;
}

function closeCreateInvitation(): void {
  isCreateInvitationOpen.value = false;
  clearSaveError();
}

function openEditInvitation(invitationId: string): void {
  clearSaveError();
  selectedInvitationId.value = invitationId;
  editFormState.value = { canSubmit: false, isSubmitting: false };
  isEditInvitationOpen.value = true;
}

function closeEditInvitation(): void {
  isEditInvitationOpen.value = false;
  selectedInvitationId.value = null;
  clearSaveError();
}

function openDeleteInvitation(invitationId: string): void {
  clearDeleteError();
  selectedInvitationId.value = invitationId;
  isDeleteInvitationOpen.value = true;
}

function closeDeleteInvitation(): void {
  isDeleteInvitationOpen.value = false;
  selectedInvitationId.value = null;
  clearDeleteError();
}

async function handleCreateInvitationSubmit(payload: AccountInviteWritePayload): Promise<void> {
  const wasCreated = await createInvite(payload);

  if (wasCreated) {
    closeCreateInvitation();
  }
}

async function handleEditInvitationSubmit(payload: AccountInviteWritePayload): Promise<void> {
  if (!selectedInvitation.value) {
    return;
  }

  const wasUpdated = await updateInvite(selectedInvitation.value.id, payload);

  if (wasUpdated) {
    closeEditInvitation();
  }
}

async function confirmDeleteInvitation(): Promise<void> {
  if (!selectedInvitation.value) {
    return;
  }

  const wasDeleted = await deleteInvite(selectedInvitation.value.id, accountId.value);

  if (wasDeleted) {
    closeDeleteInvitation();
  }
}

function handleCreateFormStateChange(state: FormState): void {
  createFormState.value = state;
}

function handleEditFormStateChange(state: FormState): void {
  editFormState.value = state;
}

function reloadInvitations(): void {
  if (!accountId.value) {
    return;
  }

  void loadInvites(accountId.value, activeFilters.value, {
    reset: true,
    perPage: invitationsPerPage.value,
  });
}

function isInvitationStatus(value: string): value is AccountInviteStatus {
  return value === 'pending' || value === 'accepted' || value === 'declined';
}

function handleLoadMoreRetry(): void {
  void loadMoreInvites();
}

function infiniteStatusLabel(): string {
  if (isLoadingMore.value) {
    return 'Cargando más invitaciones...';
  }

  if (hasReachedEnd.value) {
    return 'Has llegado al final.';
  }

  return 'Sigue desplazándote para revisar más invitaciones conforme se amplíe la colaboración.';
}
</script>

<template>
  <section class="space-y-4">
    <AccountInvitationsToolbar
      v-model:search-term="searchTerm"
      @create="openCreateInvitation"
      @open-filters="openFilters"
    />

    <section
      v-if="loadError && hasInvites"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">{{ loadError }}</AppText>
    </section>

    <AppListState
      :error="loadError"
      :has-items="hasInvites"
      :is-loading="isLoading"
      loading-label="Cargando invitaciones..."
      @retry="reloadInvitations"
    >
      <AccountInvitationsList :invitations="invites" @delete="openDeleteInvitation">
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
