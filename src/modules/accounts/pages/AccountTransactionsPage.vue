<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

import type { Account } from '@/modules/accounts/types';
import {
  AppAvatarValueRow,
  AppButton,
  AppCard,
  AppHeroMetric,
  AppIconButton,
  AppInput,
  AppModal,
  AppSectionBar,
  AppText,
} from '@/modules/shared/components';
import { useTransactionsCrud } from '@/modules/transactions/composables/useTransactionsCrud';
import TransactionsForm from '@/modules/transactions/components/TransactionsForm.vue';
import TransactionListItem from '@/modules/transactions/components/TransactionListItem.vue';
import type { TransactionWritePayload } from '@/modules/transactions/types';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const props = defineProps<{
  account?: Account;
}>();

const route = useRoute();

const isCreateTransactionModalOpen = ref(false);
const isEditTransactionModalOpen = ref(false);
const isDeleteTransactionModalOpen = ref(false);
const isFiltersOpen = ref(false);
const searchTerm = ref('');
const selectedStatuses = ref<Array<'completed' | 'pending'>>([]);
const selectedTypes = ref<Array<'income' | 'expense'>>([]);
const selectedTransactionId = ref<string | null>(null);
const createFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });
const editFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });

const transactionStatusOptions = [
  { value: 'completed', label: 'Completado' },
  { value: 'pending', label: 'Pendiente' },
] as const;

const transactionTypeOptions = [
  { value: 'income', label: 'Ingreso' },
  { value: 'expense', label: 'Egreso' },
] as const;

const accountId = computed(() =>
  typeof route.params.accountId === 'string' ? route.params.accountId : '',
);

const accountUsers = computed(() => props.account?.users ?? []);
const usersWithPendingExpenses = computed(() =>
  accountUsers.value.filter((user) => user.pendingExpenses > 0),
);
const financialGoals: Array<{ id: string; name: string; description?: string | null }> = [];

const {
  transactions,
  hasTransactions,
  isLoading,
  isSaving,
  isDeleting,
  loadError,
  saveError,
  deleteError,
  clearSaveError,
  clearDeleteError,
  loadTransactions,
  createTransaction,
  updateTransaction,
  deleteTransaction,
} = useTransactionsCrud();

const filteredTransactionItems = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return transactions.value.filter((transaction) => {
    const matchesQuery =
      normalizedQuery.length === 0 || transaction.concept.toLowerCase().includes(normalizedQuery);

    if (!matchesQuery) {
      return false;
    }

    if (
      selectedStatuses.value.length > 0 &&
      !selectedStatuses.value.includes(transaction.status ?? 'completed')
    ) {
      return false;
    }

    if (selectedTypes.value.length > 0 && !selectedTypes.value.includes(transaction.type)) {
      return false;
    }

    return true;
  });
});

const selectedTransaction = computed(() => {
  if (!selectedTransactionId.value) {
    return null;
  }

  return (
    transactions.value.find((transaction) => transaction.id === selectedTransactionId.value) ?? null
  );
});

const createTransactionActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-transaction',
    label: isSaving.value ? 'Guardando...' : 'Crear transacción',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'transaction-form',
    disabled: !createFormState.value.canSubmit || isSaving.value,
  },
]);

const editTransactionActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-edit-transaction',
    label: isSaving.value ? 'Guardando...' : 'Guardar cambios',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'edit-transaction-form',
    disabled: !editFormState.value.canSubmit || isSaving.value,
  },
]);

const deleteTransactionActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'confirm-delete-transaction',
    label: isDeleting.value ? 'Eliminando...' : 'Eliminar transacción',
    tone: 'primary' as const,
    disabled: !selectedTransaction.value || isDeleting.value,
  },
]);

watch(
  accountId,
  (nextAccountId) => {
    if (!nextAccountId) {
      return;
    }

    void loadTransactions(nextAccountId);
  },
  { immediate: true },
);

function formatDateLabel(date: string): string {
  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(`${date}T00:00:00`));
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}

function openCreateTransactionModal(): void {
  clearSaveError();
  createFormState.value = { canSubmit: false, isSubmitting: false };
  isCreateTransactionModalOpen.value = true;
}

function closeCreateTransactionModal(): void {
  isCreateTransactionModalOpen.value = false;
  clearSaveError();
}

function openEditTransaction(transactionId: string): void {
  clearSaveError();
  selectedTransactionId.value = transactionId;
  editFormState.value = { canSubmit: false, isSubmitting: false };
  isEditTransactionModalOpen.value = true;
}

function closeEditTransactionModal(): void {
  isEditTransactionModalOpen.value = false;
  selectedTransactionId.value = null;
  clearSaveError();
}

function openDeleteTransaction(transactionId: string): void {
  clearDeleteError();
  selectedTransactionId.value = transactionId;
  isDeleteTransactionModalOpen.value = true;
}

function closeDeleteTransactionModal(): void {
  isDeleteTransactionModalOpen.value = false;
  selectedTransactionId.value = null;
  clearDeleteError();
}

function openFilters(): void {
  isFiltersOpen.value = true;
}

function closeFilters(): void {
  isFiltersOpen.value = false;
}

function clearFilters(): void {
  selectedStatuses.value = [];
  selectedTypes.value = [];
}

function toggleStatus(status: 'completed' | 'pending'): void {
  if (selectedStatuses.value.includes(status)) {
    selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
    return;
  }

  selectedStatuses.value = [...selectedStatuses.value, status];
}

function toggleType(type: 'income' | 'expense'): void {
  if (selectedTypes.value.includes(type)) {
    selectedTypes.value = selectedTypes.value.filter((item) => item !== type);
    return;
  }

  selectedTypes.value = [...selectedTypes.value, type];
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

async function handleTransactionSubmit(payload: TransactionWritePayload): Promise<void> {
  const wasCreated = await createTransaction(payload);

  if (wasCreated) {
    closeCreateTransactionModal();
  }
}

async function handleEditTransactionSubmit(payload: TransactionWritePayload): Promise<void> {
  if (!selectedTransaction.value) {
    return;
  }

  const wasUpdated = await updateTransaction(selectedTransaction.value.id, payload);

  if (wasUpdated) {
    closeEditTransactionModal();
  }
}

async function confirmDeleteTransaction(): Promise<void> {
  if (!selectedTransaction.value) {
    return;
  }

  const wasDeleted = await deleteTransaction(selectedTransaction.value.id, accountId.value);

  if (wasDeleted) {
    closeDeleteTransactionModal();
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
  <section class="space-y-4">
    <AppCard class="rounded-3xl">
      <div class="space-y-5">
        <AppHeroMetric label="Balance" :value="formatCurrency(props.account?.balance ?? 0)">
          <template #adornment>
            <div
              class="flex h-12 w-12 items-center justify-center rounded-full border bg-(--app-color-surface-muted)"
              :style="{ borderColor: 'var(--app-color-border)' }"
            >
              <ArrowPathIcon class="h-5 w-5 text-(--app-color-text-subtle)" />
            </div>
          </template>
        </AppHeroMetric>

        <div class="border-t" :style="{ borderColor: 'var(--app-color-border)' }"></div>

        <div class="space-y-3">
          <AppText size="sm" tone="subtle">Pendientes por usuario</AppText>

          <div v-if="usersWithPendingExpenses.length > 0" class="space-y-2">
            <AppAvatarValueRow
              v-for="user in usersWithPendingExpenses"
              :key="user.id"
              :name="user.name"
              :seed="user.id"
              :value="formatCurrency(user.pendingExpenses)"
            />
          </div>

          <div v-else class="rounded-2xl bg-(--app-color-surface-muted) px-3 py-3">
            <AppText size="sm" tone="subtle">No hay montos pendientes por usuario.</AppText>
          </div>
        </div>
      </div>
    </AppCard>

    <AppSectionBar title="Transacciones">
      <template #actions>
        <AppButton variant="primary" @click="openCreateTransactionModal">
          <PlusIcon class="h-4 w-4" />
          <span>Nueva</span>
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
          id="transaction-search"
          v-model="searchTerm"
          type="search"
          placeholder="Buscar transacción por concepto"
          class="pl-11"
        />
      </div>

      <AppIconButton ariaLabel="Abrir filtros avanzados" @click="openFilters">
        <AdjustmentsHorizontalIcon class="h-5 w-5" />
      </AppIconButton>
    </div>

    <section
      v-if="loadError && hasTransactions"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">{{ loadError }}</AppText>
    </section>

    <section v-if="isLoading && !hasTransactions" class="rounded-2xl border px-4 py-10 text-center">
      <AppText>Cargando transacciones...</AppText>
    </section>

    <section v-else class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle">
          {{ filteredTransactionItems.length }} transacciones visibles
        </AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <TransactionListItem
          v-for="transaction in filteredTransactionItems"
          :key="transaction.id"
          :amount="transaction.amount"
          :concept="transaction.concept"
          :date-label="formatDateLabel(transaction.date)"
          :item-id="transaction.id"
          :status="transaction.status ?? 'completed'"
          :type="transaction.type"
          @delete="openDeleteTransaction"
          @edit="openEditTransaction"
        />

        <div
          v-if="filteredTransactionItems.length === 0"
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm"
            >No hay transacciones que coincidan con la búsqueda o los filtros actuales.</AppText
          >
        </div>

        <div
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">
            Sigue desplazándote para revisar más actividad conforme la cuenta acumule movimientos.
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
          <AppText>Refina la actividad según el estado de conciliación de cada movimiento.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="status in transactionStatusOptions"
            :key="status.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
            :class="
              selectedStatuses.includes(status.value)
                ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleStatus(status.value)"
          >
            {{ status.label }}
          </button>
        </div>

        <div class="space-y-2">
          <AppTitle as="h2" size="sm">Tipo</AppTitle>
          <AppText>Filtra entre ingresos y egresos.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="type in transactionTypeOptions"
            :key="type.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
            :class="
              selectedTypes.includes(type.value)
                ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleType(type.value)"
          >
            {{ type.label }}
          </button>
        </div>
      </div>
    </AppModal>

    <AppModal
      :open="isCreateTransactionModalOpen"
      :actions="createTransactionActions"
      title="Nueva transacción"
      variant="default"
      @close="closeCreateTransactionModal"
    >
      <TransactionsForm
        form-id="transaction-form"
        :account-users="accountUsers"
        :financial-goals="financialGoals"
        :locked-account-id="accountId"
        :server-error="saveError"
        @state-change="handleCreateFormStateChange"
        @submit="handleTransactionSubmit"
      />
    </AppModal>

    <AppModal
      :open="isEditTransactionModalOpen"
      :actions="editTransactionActions"
      title="Editar transacción"
      variant="default"
      @close="closeEditTransactionModal"
    >
      <TransactionsForm
        v-if="selectedTransaction"
        form-id="edit-transaction-form"
        :account-users="accountUsers"
        :financial-goals="financialGoals"
        :locked-account-id="accountId"
        :initial-values="selectedTransaction"
        :server-error="saveError"
        @state-change="handleEditFormStateChange"
        @submit="handleEditTransactionSubmit"
      />
    </AppModal>

    <AppModal
      :open="isDeleteTransactionModalOpen"
      :actions="deleteTransactionActions"
      title="Eliminar transacción"
      variant="danger"
      @action="$event === 'confirm-delete-transaction' && confirmDeleteTransaction()"
      @close="closeDeleteTransactionModal"
    >
      <div class="space-y-3">
        <AppText v-if="selectedTransaction">
          Vas a eliminar
          <strong>{{ selectedTransaction.concept }}</strong
          >.
        </AppText>
        <AppText v-if="deleteError" class="text-(--app-color-danger)!">{{ deleteError }}</AppText>
      </div>
    </AppModal>
  </section>
</template>
