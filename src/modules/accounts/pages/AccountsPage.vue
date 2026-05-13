<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, onMounted, ref } from 'vue';

import AccountsForm from '@/modules/accounts/components/AccountsForm.vue';
import AccountListItem from '@/modules/accounts/components/AccountListItem.vue';
import { useAccountsCrud } from '@/modules/accounts/composables/useAccountsCrud';
import type { Account, AccountStatus, AccountWritePayload } from '@/modules/accounts/types';
import {
  AppButton,
  AppIconButton,
  AppInput,
  AppModal,
  AppSectionBar,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const searchTerm = ref('');
const isCreateAccountOpen = ref(false);
const isDeleteAccountOpen = ref(false);
const isEditAccountOpen = ref(false);
const isFiltersOpen = ref(false);
const selectedStatuses = ref<AccountStatus[]>([]);
const selectedAccountId = ref<string | null>(null);
const editAccountInitialValues = ref<Partial<Account> | null>(null);
const createFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });
const editFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });

const statusOptions = ['Activo', 'Inactivo'] as const;

const {
  accounts,
  hasAccounts,
  isLoading,
  isSaving,
  isDeleting,
  loadError,
  saveError,
  deleteError,
  clearSaveError,
  clearDeleteError,
  loadAccounts,
  createAccount,
  updateAccount,
  deleteAccount,
} = useAccountsCrud();

const filteredAccounts = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return accounts.value.filter((account) => {
    const matchesQuery = account.name.toLowerCase().includes(normalizedQuery);

    if (!matchesQuery) {
      return false;
    }

    if (selectedStatuses.value.length === 0) {
      return true;
    }

    return selectedStatuses.value.includes(account.status);
  });
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

onMounted(() => {
  void loadAccounts();
});

function toggleStatus(status: AccountStatus): void {
  if (selectedStatuses.value.includes(status)) {
    selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
    return;
  }

  selectedStatuses.value = [...selectedStatuses.value, status];
}

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

function clearFilters(): void {
  selectedStatuses.value = [];
}

function handleFiltersModalAction(actionKey: string): void {
  if (actionKey === 'clear') {
    clearFilters();
    return;
  }

  if (actionKey === 'close') {
    closeFilters();
  }
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
    <AppSectionBar
      title="Gestión de cuentas"
      description="La navegación por default es entrar al detalle de cada cuenta."
    >
      <template #actions>
        <AppButton variant="primary" @click="openCreateAccount">
          <PlusIcon class="h-4 w-4" />
        </AppButton>
      </template>
    </AppSectionBar>

    <div class="flex items-center gap-3">
      <div class="relative flex-1">
        <div
          class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-(--app-color-text-subtle)"
        >
          <MagnifyingGlassIcon class="h-5 w-5" />
        </div>
        <AppInput
          id="account-search"
          v-model="searchTerm"
          type="search"
          placeholder="Buscar cuenta por nombre"
          class="pl-11"
        />
      </div>

      <AppIconButton ariaLabel="Abrir filtros avanzados" @click="openFilters">
        <AdjustmentsHorizontalIcon class="h-5 w-5" />
      </AppIconButton>
    </div>

    <section
      v-if="loadError && hasAccounts"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">
        {{ loadError }}
      </AppText>
    </section>

    <section v-if="isLoading && !hasAccounts" class="rounded-2xl border px-4 py-10 text-center">
      <AppText>Cargando cuentas...</AppText>
    </section>

    <section
      v-else-if="loadError && !hasAccounts"
      class="space-y-3 rounded-2xl border px-4 py-6 text-center"
    >
      <AppText>{{ loadError }}</AppText>
      <div class="flex justify-center">
        <AppButton variant="outline" @click="loadAccounts"> Reintentar </AppButton>
      </div>
    </section>

    <section v-else class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle"> {{ filteredAccounts.length }} cuentas visibles </AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <AccountListItem
          v-for="account in filteredAccounts"
          :key="account.id"
          :account="account"
          @delete="openDeleteAccount"
          @edit="openEditAccount"
        />

        <div
          v-if="filteredAccounts.length === 0"
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">
            No hay cuentas que coincidan con la búsqueda o los filtros actuales.
          </AppText>
        </div>

        <div
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">
            Sigue desplazándote para explorar más cuentas cuando la facility crezca.
          </AppText>
        </div>
      </div>
    </section>

    <AppModal
      :open="isFiltersOpen"
      :actions="[
        { key: 'clear', label: 'Limpiar filtros', tone: 'neutral', icon: ArrowPathIcon },
        { key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true },
      ]"
      title="Filtros avanzados"
      variant="default"
      @action="handleFiltersModalAction"
      @close="closeFilters"
    >
      <div class="space-y-5">
        <div class="space-y-2">
          <AppTitle as="h2" size="sm">Estatus</AppTitle>
          <AppText>Refina la lista usando el estado operativo de la cuenta.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="status in statusOptions"
            :key="status"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
            :class="
              selectedStatuses.includes(status)
                ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleStatus(status)"
          >
            {{ status }}
          </button>
        </div>
      </div>
    </AppModal>

    <AppModal
      :open="isCreateAccountOpen"
      :actions="createAccountActions"
      title="Nueva cuenta"
      variant="default"
      @close="closeCreateAccount"
    >
      <AccountsForm
        form-id="account-form"
        :server-error="saveError"
        @state-change="handleCreateFormStateChange"
        @submit="handleCreateAccountSubmit"
      />
    </AppModal>

    <AppModal
      :open="isEditAccountOpen"
      :actions="editAccountActions"
      title="Editar cuenta"
      variant="default"
      @close="closeEditAccount"
    >
      <AccountsForm
        v-if="editAccountInitialValues"
        :key="selectedAccountId ?? 'edit-account-form'"
        form-id="edit-account-form"
        :initial-values="editAccountInitialValues"
        :server-error="saveError"
        @state-change="handleEditFormStateChange"
        @submit="handleEditAccountSubmit"
      />
    </AppModal>

    <AppModal
      :open="isDeleteAccountOpen"
      :actions="deleteAccountActions"
      title="Eliminar cuenta"
      variant="danger"
      @action="$event === 'confirm-delete-account' && confirmDeleteAccount()"
      @close="closeDeleteAccount"
    >
      <div class="space-y-3">
        <AppText v-if="selectedAccount">
          Vas a eliminar
          <strong>{{ selectedAccount.name }}</strong
          >.
        </AppText>
        <AppText v-if="deleteError" class="text-(--app-color-danger)!">
          {{ deleteError }}
        </AppText>
        <AppText size="sm" tone="subtle">
          Esta acción seguirá el mismo flujo de confirmación antes de conectarse a persistencia
          real.
        </AppText>
      </div>
    </AppModal>
  </div>
</template>
