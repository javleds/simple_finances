<script setup lang="ts">
import {
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

import AccountDeleteModal from '@/modules/accounts/components/AccountDeleteModal.vue';
import AccountFiltersModal from '@/modules/accounts/components/AccountFiltersModal.vue';
import AccountFormModal from '@/modules/accounts/components/AccountFormModal.vue';
import AccountsList from '@/modules/accounts/components/AccountsList.vue';
import AccountsToolbar from '@/modules/accounts/components/AccountsToolbar.vue';
import { useAccountsCrud } from '@/modules/accounts/composables/useAccountsCrud';
import { useAccountFilters } from '@/modules/accounts/composables/useAccountFilters';
import type {
  Account,
  AccountKindFilter,
  AccountSurfaceFilter,
  AccountStatus,
  AccountWritePayload,
} from '@/modules/accounts/types';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import {
  AppListState,
  AppLoadMoreFooter,
  AppText,
} from '@/modules/shared/components';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const route = useRoute();

const statusOptions = ['Activo', 'Inactivo'] as const;
const defaultAccountsPerPage = 20;
const kindOptions = [
  { value: 'credit', label: 'Crédito' },
  { value: 'debit', label: 'Débito' },
] as const;
const surfaceOptions = [
  { value: 'virtual', label: 'Virtual' },
  { value: 'physical', label: 'Física' },
] as const;
const isCreateAccountOpen = ref(false);
const isDeleteAccountOpen = ref(false);
const isEditAccountOpen = ref(false);
const isFiltersOpen = ref(false);
const selectedAccountId = ref<string | null>(null);
const editAccountInitialValues = ref<Partial<Account> | null>(null);
const createFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });
const editFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });
const {
  activeFilters,
  clearFilters,
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
} = useAccountsCrud();

const accountsPerPage = computed(() => {
  const rawValue = typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

  if (!Number.isInteger(rawValue) || rawValue <= 0) {
    return defaultAccountsPerPage;
  }

  return rawValue;
});

const { target: loadMoreSentinel } = useInfiniteScroll({
  enabled: computed(() => !isLoading.value && !isLoadingMore.value && hasMoreAccounts.value),
  onIntersect: () => {
    void loadMoreAccounts();
  },
});

const selectedAccount = computed(() => {
  if (!selectedAccountId.value) {
    return null;
  }

  return accounts.value.find((account) => account.id === selectedAccountId.value) ?? null;
});

const createAccountActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-account',
    label: isSaving.value ? 'Guardando...' : 'Crear cuenta',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'account-form',
    disabled: !createFormState.value.canSubmit || isSaving.value,
  },
]);

const editAccountActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-edit-account',
    label: isSaving.value ? 'Guardando...' : 'Guardar cambios',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'edit-account-form',
    disabled: !editFormState.value.canSubmit || isSaving.value,
  },
]);

const deleteAccountActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'confirm-delete-account',
    label: isDeleting.value ? 'Eliminando...' : 'Eliminar cuenta',
    tone: 'primary' as const,
    disabled: !selectedAccount.value || isDeleting.value,
  },
]);

watch(
  [activeFilters, accountsPerPage],
  ([nextFilters, nextPerPage]) => {
    void loadAccounts(nextFilters, {
      reset: true,
      perPage: nextPerPage,
    });
  },
  { immediate: true },
);

function openFilters(): void {
  isFiltersOpen.value = true;
}

function openCreateAccount(): void {
  clearSaveError();
  createFormState.value = { canSubmit: false, isSubmitting: false };
  isCreateAccountOpen.value = true;
}

function closeCreateAccount(): void {
  isCreateAccountOpen.value = false;
  clearSaveError();
}

function openEditAccount(accountId: string): void {
  const account = accounts.value.find((item) => item.id === accountId);

  if (!account) {
    return;
  }

  clearSaveError();
  selectedAccountId.value = accountId;
  editAccountInitialValues.value = {
    ...account,
    users: [...account.users],
  };
  editFormState.value = { canSubmit: false, isSubmitting: false };
  isEditAccountOpen.value = true;
}

function closeEditAccount(): void {
  isEditAccountOpen.value = false;
  selectedAccountId.value = null;
  editAccountInitialValues.value = null;
  clearSaveError();
}

function openDeleteAccount(accountId: string): void {
  clearDeleteError();
  selectedAccountId.value = accountId;
  isDeleteAccountOpen.value = true;
}

function closeDeleteAccount(): void {
  isDeleteAccountOpen.value = false;
  selectedAccountId.value = null;
  clearDeleteError();
}

function closeFilters(): void {
  isFiltersOpen.value = false;
}

function reloadAccounts(): void {
  void loadAccounts(activeFilters.value, {
    reset: true,
    perPage: accountsPerPage.value,
  });
}

function handleLoadMoreRetry(): void {
  void loadMoreAccounts();
}

function infiniteStatusLabel(): string {
  if (isLoadingMore.value) {
    return 'Cargando más cuentas...';
  }

  if (hasReachedEnd.value) {
    return 'Has llegado al final.';
  }

  return 'Sigue desplazándote para explorar más cuentas cuando la facility crezca.';
}

async function handleCreateAccountSubmit(payload: AccountWritePayload): Promise<void> {
  const wasCreated = await createAccount(payload);

  if (wasCreated) {
    closeCreateAccount();
  }
}

async function handleEditAccountSubmit(payload: AccountWritePayload): Promise<void> {
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

  const wasDeleted = await deleteAccount(selectedAccount.value.id);

  if (wasDeleted) {
    closeDeleteAccount();
  }
}

function handleCreateFormStateChange(state: FormState): void {
  createFormState.value = state;
}

function handleEditFormStateChange(state: FormState): void {
  editFormState.value = state;
}

</script>

<template>
  <div class="space-y-5">
    <AccountsToolbar
      v-model:search-term="searchTerm"
      @create="openCreateAccount"
      @open-filters="openFilters"
    />

    <section v-if="loadError && hasAccounts" class="rounded-2xl border border-(--app-color-danger) px-4 py-3">
      <AppText class="text-(--app-color-danger)!">{{ loadError }}</AppText>
    </section>

    <AppListState
      :error="loadError"
      :has-items="hasAccounts"
      :is-loading="isLoading"
      loading-label="Cargando cuentas..."
      @retry="reloadAccounts"
    >
      <AccountsList :accounts="accounts" @delete="openDeleteAccount" @edit="openEditAccount">
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
      @clear="clearFilters"
      @close="closeFilters"
      @toggle-kind="toggleKind"
      @toggle-status="toggleStatus"
      @toggle-surface="toggleSurface"
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
      @close="closeDeleteAccount"
      @confirm="confirmDeleteAccount"
    />
  </div>
</template>
