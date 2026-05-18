<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import AccountsForm from '@/modules/accounts/components/AccountsForm.vue';
import AccountListItem from '@/modules/accounts/components/AccountListItem.vue';
import { useAccountsCrud } from '@/modules/accounts/composables/useAccountsCrud';
import type { Account, AccountStatus, AccountWritePayload } from '@/modules/accounts/types';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
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
type AccountKindFilter = 'credit' | 'debit';
type AccountSurfaceFilter = 'virtual' | 'physical';

const searchTerm = ref('');
const route = useRoute();
const router = useRouter();
const isCreateAccountOpen = ref(false);
const isDeleteAccountOpen = ref(false);
const isEditAccountOpen = ref(false);
const isFiltersOpen = ref(false);
const selectedStatuses = ref<AccountStatus[]>([]);
const selectedKinds = ref<AccountKindFilter[]>([]);
const selectedSurfaces = ref<AccountSurfaceFilter[]>([]);
const selectedAccountId = ref<string | null>(null);
const editAccountInitialValues = ref<Partial<Account> | null>(null);
const createFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });
const editFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });

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
const availableStatuses = [...statusOptions];
const availableKinds = kindOptions.map((option) => option.value);
const availableSurfaces = surfaceOptions.map((option) => option.value);

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

const filteredAccounts = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return accounts.value.filter((account) => {
    const matchesQuery = account.name.toLowerCase().includes(normalizedQuery);

    if (!matchesQuery) {
      return false;
    }

    if (selectedStatuses.value.length === 0) {
      if (
        selectedKinds.value.length > 0 &&
        !selectedKinds.value.includes(account.isCredit ? 'credit' : 'debit')
      ) {
        return false;
      }

      if (
        selectedSurfaces.value.length > 0 &&
        !selectedSurfaces.value.includes(account.isVirtual ? 'virtual' : 'physical')
      ) {
        return false;
      }

      return true;
    }

    if (!selectedStatuses.value.includes(account.status)) {
      return false;
    }

    if (
      selectedKinds.value.length > 0 &&
      !selectedKinds.value.includes(account.isCredit ? 'credit' : 'debit')
    ) {
      return false;
    }

    if (
      selectedSurfaces.value.length > 0 &&
      !selectedSurfaces.value.includes(account.isVirtual ? 'virtual' : 'physical')
    ) {
      return false;
    }

    return true;
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

watch(
  accountsPerPage,
  (nextPerPage) => {
    void loadAccounts({
      reset: true,
      perPage: nextPerPage,
    });
  },
  { immediate: true },
);

watch(
  () => route.query,
  (nextQuery) => {
    searchTerm.value = typeof nextQuery.search === 'string' ? nextQuery.search : '';
    selectedStatuses.value = parseQueryValues(nextQuery.status, isAccountStatus);
    selectedKinds.value = parseQueryValues(nextQuery.kind, isAccountKindFilter);
    selectedSurfaces.value = parseQueryValues(nextQuery.surface, isAccountSurfaceFilter);
  },
  { immediate: true },
);

watch(
  [searchTerm, selectedStatuses, selectedKinds, selectedSurfaces],
  () => {
    const nextQuery = {
      ...route.query,
      search: searchTerm.value.trim() ? searchTerm.value.trim() : undefined,
      status: selectedStatuses.value.length > 0 ? selectedStatuses.value.join(',') : undefined,
      kind: selectedKinds.value.length > 0 ? selectedKinds.value.join(',') : undefined,
      surface: selectedSurfaces.value.length > 0 ? selectedSurfaces.value.join(',') : undefined,
    };

    if (areQueriesEqual(route.query, nextQuery)) {
      return;
    }

    void router.replace({ query: nextQuery });
  },
  { deep: true },
);

function toggleStatus(status: AccountStatus): void {
  if (selectedStatuses.value.includes(status)) {
    selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
    return;
  }

  selectedStatuses.value = [...selectedStatuses.value, status];
}

function toggleKind(kind: AccountKindFilter): void {
  if (selectedKinds.value.includes(kind)) {
    selectedKinds.value = selectedKinds.value.filter((item) => item !== kind);
    return;
  }

  selectedKinds.value = [...selectedKinds.value, kind];
}

function toggleSurface(surface: AccountSurfaceFilter): void {
  if (selectedSurfaces.value.includes(surface)) {
    selectedSurfaces.value = selectedSurfaces.value.filter((item) => item !== surface);
    return;
  }

  selectedSurfaces.value = [...selectedSurfaces.value, surface];
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
  searchTerm.value = '';
  selectedStatuses.value = [];
  selectedKinds.value = [];
  selectedSurfaces.value = [];
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

function reloadAccounts(): void {
  void loadAccounts({
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

function parseQueryValues<TValue extends string>(
  value: unknown,
  isAllowedValue: (value: string) => value is TValue,
): TValue[] {
  if (typeof value !== 'string' || value.trim().length === 0) {
    return [];
  }

  return value
    .split(',')
    .map((item) => item.trim())
    .filter(isAllowedValue);
}

function areQueriesEqual(
  currentQuery: Record<string, unknown>,
  nextQuery: Record<string, unknown>,
): boolean {
  const normalizedCurrent = normalizeQueryRecord(currentQuery);
  const normalizedNext = normalizeQueryRecord(nextQuery);

  return JSON.stringify(normalizedCurrent) === JSON.stringify(normalizedNext);
}

function normalizeQueryRecord(query: Record<string, unknown>): Record<string, string> {
  return Object.entries(query).reduce<Record<string, string>>((accumulator, [key, value]) => {
    if (typeof value === 'string' && value.length > 0) {
      accumulator[key] = value;
    }

    return accumulator;
  }, {});
}

function isAccountStatus(value: string): value is AccountStatus {
  return availableStatuses.includes(value as AccountStatus);
}

function isAccountKindFilter(value: string): value is AccountKindFilter {
  return availableKinds.includes(value as AccountKindFilter);
}

function isAccountSurfaceFilter(value: string): value is AccountSurfaceFilter {
  return availableSurfaces.includes(value as AccountSurfaceFilter);
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

    <section v-if="isLoading && !hasAccounts" class="rounded-2xl border px-4 py-10 text-center">
      <AppText>Cargando cuentas...</AppText>
    </section>

    <section
      v-else-if="loadError && !hasAccounts"
      class="space-y-3 rounded-2xl border px-4 py-6 text-center"
    >
      <AppText>{{ loadError }}</AppText>
      <div class="flex justify-center">
        <AppButton variant="outline" @click="reloadAccounts"> Reintentar </AppButton>
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
            {{ infiniteStatusLabel() }}
          </AppText>
        </div>

        <div
          v-if="loadError && hasAccounts"
          class="rounded-2xl border border-(--app-color-danger) px-4 py-4 text-center"
        >
          <AppText size="sm" class="text-(--app-color-danger)!">
            {{ loadError }}
          </AppText>
          <div class="mt-3 flex justify-center">
            <AppButton variant="secondary" @click="handleLoadMoreRetry">Reintentar</AppButton>
          </div>
        </div>

        <div
          v-if="hasMoreAccounts || isLoadingMore || hasReachedEnd"
          ref="loadMoreSentinel"
          class="h-1 w-full"
          aria-hidden="true"
        />
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

        <div class="space-y-2">
          <AppTitle as="h2" size="sm">Tipo de cuenta</AppTitle>
          <AppText>Filtra entre cuentas de crédito y débito.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="kind in kindOptions"
            :key="kind.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
            :class="
              selectedKinds.includes(kind.value)
                ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleKind(kind.value)"
          >
            {{ kind.label }}
          </button>
        </div>

        <div class="space-y-2">
          <AppTitle as="h2" size="sm">Superficie</AppTitle>
          <AppText>Filtra entre cuentas virtuales y físicas.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="surface in surfaceOptions"
            :key="surface.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
            :class="
              selectedSurfaces.includes(surface.value)
                ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleSurface(surface.value)"
          >
            {{ surface.label }}
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
