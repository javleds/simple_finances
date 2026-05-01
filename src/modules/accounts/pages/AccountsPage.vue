<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

import AccountsForm from '@/modules/accounts/components/AccountsForm.vue';
import AccountListItem from '@/modules/accounts/components/AccountListItem.vue';
import { accounts } from '@/modules/accounts/data/accounts';
import {
  AppButton,
  AppIconButton,
  AppInput,
  AppModal,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

const searchTerm = ref('');
const isCreateAccountOpen = ref(false);
const isDeleteAccountOpen = ref(false);
const isEditAccountOpen = ref(false);
const isFiltersOpen = ref(false);
const selectedStatuses = ref<string[]>([]);
const selectedAccountId = ref<string | null>(null);

const statusOptions = ['Activo', 'Inactivo'] as const;

const filteredAccounts = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return accounts.filter((account) => {
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

  return accounts.find((account) => account.id === selectedAccountId.value) ?? null;
});

const selectedAccountFormValues = computed(() => {
  if (!selectedAccount.value) {
    return null;
  }

  return {
    name: selectedAccount.value.name,
    color: selectedAccount.value.color,
    description: selectedAccount.value.description,
    isVirtual: selectedAccount.value.isVirtual,
    isCredit: selectedAccount.value.accountType === 'credito',
    creditLine: parseCurrencyValue(selectedAccount.value.creditLine),
    closingDay: 15,
  };
});

function toggleStatus(status: string): void {
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
  isCreateAccountOpen.value = true;
}

function closeCreateAccount(): void {
  isCreateAccountOpen.value = false;
}

function openEditAccount(accountId: string): void {
  selectedAccountId.value = accountId;
  isEditAccountOpen.value = true;
}

function closeEditAccount(): void {
  isEditAccountOpen.value = false;
  selectedAccountId.value = null;
}

function openDeleteAccount(accountId: string): void {
  selectedAccountId.value = accountId;
  isDeleteAccountOpen.value = true;
}

function closeDeleteAccount(): void {
  isDeleteAccountOpen.value = false;
  selectedAccountId.value = null;
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

function handleCreateAccountSubmit(): void {
  closeCreateAccount();
}

function handleEditAccountSubmit(): void {
  closeEditAccount();
}

function confirmDeleteAccount(): void {
  closeDeleteAccount();
}

function parseCurrencyValue(value: string): number | null {
  const normalizedValue = value.replace(/[^0-9.-]/g, '');
  const nextValue = Number(normalizedValue);

  if (Number.isNaN(nextValue)) {
    return null;
  }

  return nextValue;
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between gap-3">
      <div class="space-y-1">
        <AppTitle as="h2" size="sm">Gestión de cuentas</AppTitle>
        <AppText>La navegación por default es entrar al detalle de cada cuenta.</AppText>
      </div>

      <AppButton variant="primary" @click="openCreateAccount">
        <PlusIcon class="h-4 w-4" />
      </AppButton>
    </div>

    <div class="flex items-center gap-3">
      <div class="relative flex-1">
        <div
          class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[var(--app-color-text-subtle)]"
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

    <section class="space-y-3">
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
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
            :class="
              selectedStatuses.includes(status)
                ? 'bg-[var(--app-color-primary)] text-[var(--app-color-primary-foreground)]'
                : 'bg-[var(--app-color-surface-muted)] text-[var(--app-color-text)]'
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
      :actions="[
        { key: 'close', label: 'Cancelar', tone: 'danger', icon: XMarkIcon, autoClose: true },
        {
          key: 'submit-account',
          label: 'Crear cuenta',
          tone: 'primary',
          type: 'submit',
          form: 'account-form',
        },
      ]"
      title="Nueva cuenta"
      variant="default"
      @close="closeCreateAccount"
    >
      <AccountsForm form-id="account-form" @submit="handleCreateAccountSubmit" />
    </AppModal>

    <AppModal
      :open="isEditAccountOpen"
      :actions="[
        { key: 'close', label: 'Cancelar', tone: 'danger', icon: XMarkIcon, autoClose: true },
        {
          key: 'submit-edit-account',
          label: 'Guardar cambios',
          tone: 'primary',
          type: 'submit',
          form: 'edit-account-form',
        },
      ]"
      title="Editar cuenta"
      variant="default"
      @close="closeEditAccount"
    >
      <AccountsForm
        v-if="selectedAccountFormValues"
        form-id="edit-account-form"
        :initial-values="selectedAccountFormValues"
        @submit="handleEditAccountSubmit"
      />
    </AppModal>

    <AppModal
      :open="isDeleteAccountOpen"
      :actions="[
        { key: 'close', label: 'Cancelar', tone: 'danger', icon: XMarkIcon, autoClose: true },
        {
          key: 'confirm-delete-account',
          label: 'Eliminar cuenta',
          tone: 'primary',
        },
      ]"
      title="Eliminar cuenta"
      variant="danger"
      @action="($event === 'confirm-delete-account') && confirmDeleteAccount()"
      @close="closeDeleteAccount"
    >
      <div class="space-y-3">
        <AppText>
          Vas a eliminar
          <strong>{{ selectedAccount?.name }}</strong>.
        </AppText>
        <AppText size="sm" tone="subtle">
          Esta acción seguirá el mismo flujo de confirmación antes de conectarse a persistencia real.
        </AppText>
      </div>
    </AppModal>
  </div>
</template>
