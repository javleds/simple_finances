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

import type { Account } from '@/modules/accounts/types';
import { useAccountGoalsCrud } from '@/modules/accounts/composables/useAccountGoalsCrud';
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
  AppTitle,
} from '@/modules/shared/components';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import { areQueriesEqual, parseQueryValues } from '@/modules/shared/lib/queryParams';
import { useTransactionsCrud } from '@/modules/transactions/composables/useTransactionsCrud';
import TransactionsForm from '@/modules/transactions/components/TransactionsForm.vue';
import TransactionListItem from '@/modules/transactions/components/TransactionListItem.vue';
import type {
  TransactionListFilters,
  TransactionStatus,
  TransactionType,
  TransactionWritePayload,
} from '@/modules/transactions/types';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const props = defineProps<{
  account?: Account;
}>();

const route = useRoute();
const router = useRouter();

const isCreateTransactionModalOpen = ref(false);
const isEditTransactionModalOpen = ref(false);
const isDeleteTransactionModalOpen = ref(false);
const isFiltersOpen = ref(false);
const searchTerm = ref('');
const selectedStatuses = ref<TransactionStatus[]>([]);
const selectedTypes = ref<TransactionType[]>([]);
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
const defaultTransactionsPerPage = 20;

const accountId = computed(() =>
  typeof route.params.accountId === 'string' ? route.params.accountId : '',
);

const accountUsers = computed(() => props.account?.users ?? []);
const isSharedAccount = computed(() => accountUsers.value.length > 1);
const usersWithPendingExpenses = computed(() =>
  accountUsers.value.filter((user) => user.pendingExpenses > 0),
);
const accountBalance = ref(props.account?.balance ?? 0);
const {
  goals: financialGoals,
  isLoading: isLoadingFinancialGoals,
  loadGoals,
} = useAccountGoalsCrud();

const {
  transactions,
  hasTransactions,
  hasMoreTransactions,
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
  loadTransactions,
  loadMoreTransactions,
  createTransaction,
  updateTransaction,
  deleteTransaction,
} = useTransactionsCrud();

const transactionsPerPage = computed(() => {
  const rawValue = typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

  if (!Number.isInteger(rawValue) || rawValue <= 0) {
    return defaultTransactionsPerPage;
  }

  return rawValue;
});

const { target: loadMoreSentinel } = useInfiniteScroll({
  enabled: computed(() => !isLoading.value && !isLoadingMore.value && hasMoreTransactions.value),
  onIntersect: () => {
    void loadMoreTransactions();
  },
});

const activeFilters = computed<TransactionListFilters>(() => ({
  search: searchTerm.value.trim() || undefined,
  status: selectedStatuses.value.length > 0 ? [...selectedStatuses.value] : undefined,
  type: selectedTypes.value.length > 0 ? [...selectedTypes.value] : undefined,
}));

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
  () => props.account?.balance,
  (nextBalance) => {
    if (typeof nextBalance !== 'number') {
      return;
    }

    accountBalance.value = nextBalance;
  },
  { immediate: true },
);

watch(
  () => route.query,
  (nextQuery) => {
    searchTerm.value = typeof nextQuery.search === 'string' ? nextQuery.search : '';
    selectedStatuses.value = parseQueryValues(nextQuery.status, isTransactionStatus);
    selectedTypes.value = parseQueryValues(nextQuery.type, isTransactionType);
  },
  { immediate: true },
);

watch(
  [searchTerm, selectedStatuses, selectedTypes],
  () => {
    const nextQuery = {
      ...route.query,
      search: searchTerm.value.trim() || undefined,
      status: selectedStatuses.value.length > 0 ? selectedStatuses.value.join(',') : undefined,
      type: selectedTypes.value.length > 0 ? selectedTypes.value.join(',') : undefined,
    };

    if (areQueriesEqual(route.query, nextQuery)) {
      return;
    }

    void router.replace({ query: nextQuery });
  },
  { deep: true },
);

watch(
  [accountId, activeFilters, transactionsPerPage],
  ([nextAccountId, nextFilters, nextPerPage]) => {
    if (!nextAccountId) {
      return;
    }

    void loadTransactions(nextAccountId, nextFilters, {
      reset: true,
      perPage: nextPerPage,
    });
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

function applyMutationBalance(options: {
  accountBalance?: number | null;
  previousAccountBalance?: number | null;
}): void {
  if (typeof options.accountBalance === 'number') {
    accountBalance.value = options.accountBalance;
    return;
  }

  if (typeof options.previousAccountBalance === 'number') {
    accountBalance.value = options.previousAccountBalance;
  }
}

function openCreateTransactionModal(): void {
  clearSaveError();
  createFormState.value = { canSubmit: false, isSubmitting: false };

  if (accountId.value) {
    void loadGoals(accountId.value);
  }

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

  if (accountId.value) {
    void loadGoals(accountId.value);
  }

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

function toggleStatus(status: TransactionStatus): void {
  if (selectedStatuses.value.includes(status)) {
    selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
    return;
  }

  selectedStatuses.value = [...selectedStatuses.value, status];
}

function toggleType(type: TransactionType): void {
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
  const result = await createTransaction(payload);

  if (!result) {
    return;
  }

  applyMutationBalance(result.meta);
  closeCreateTransactionModal();
}

async function handleEditTransactionSubmit(payload: TransactionWritePayload): Promise<void> {
  if (!selectedTransaction.value) {
    return;
  }

  const result = await updateTransaction(selectedTransaction.value.id, payload);

  if (!result) {
    return;
  }

  applyMutationBalance(result.meta);
  closeEditTransactionModal();
}

async function confirmDeleteTransaction(): Promise<void> {
  if (!selectedTransaction.value) {
    return;
  }

  const result = await deleteTransaction(selectedTransaction.value.id, accountId.value);

  if (!result) {
    return;
  }

  applyMutationBalance(result.meta);
  closeDeleteTransactionModal();
}

function handleCreateFormStateChange(state: FormState): void {
  createFormState.value = state;
}

function handleEditFormStateChange(state: FormState): void {
  editFormState.value = state;
}

function isTransactionStatus(value: string): value is TransactionStatus {
  return value === 'completed' || value === 'pending';
}

function isTransactionType(value: string): value is TransactionType {
  return value === 'income' || value === 'expense';
}

function reloadTransactions(): void {
  if (!accountId.value) {
    return;
  }

  void loadTransactions(accountId.value, activeFilters.value, {
    reset: true,
    perPage: transactionsPerPage.value,
  });
}

function handleLoadMoreRetry(): void {
  void loadMoreTransactions();
}

function infiniteStatusLabel(): string {
  if (isLoadingMore.value) {
    return 'Cargando más transacciones...';
  }

  if (hasReachedEnd.value) {
    return 'Has llegado al final.';
  }

  return 'Sigue desplazándote para revisar más actividad conforme la cuenta acumule movimientos.';
}
</script>

<template>
  <section class="space-y-4">
    <AppCard class="rounded-3xl">
      <div class="space-y-5">
        <AppHeroMetric label="Balance" :value="formatCurrency(accountBalance)">
          <template #adornment>
            <div
              class="flex h-12 w-12 items-center justify-center rounded-full border bg-(--app-color-surface-muted)"
              :style="{ borderColor: 'var(--app-color-border)' }"
            >
              <ArrowPathIcon class="h-5 w-5 text-(--app-color-text-subtle)" />
            </div>
          </template>
        </AppHeroMetric>

        <template v-if="isSharedAccount">
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
        </template>
      </div>
    </AppCard>

    <AppSectionBar title="Transacciones">
      <template #actions>
        <AppButton variant="primary" @click="openCreateTransactionModal">
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

    <section
      v-else-if="loadError && !hasTransactions"
      class="space-y-3 rounded-2xl border px-4 py-6 text-center"
    >
      <AppText>{{ loadError }}</AppText>
      <div class="flex justify-center">
        <AppButton variant="secondary" @click="reloadTransactions">Reintentar</AppButton>
      </div>
    </section>

    <section v-else class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle">
          {{ transactions.length }} transacciones visibles
        </AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <TransactionListItem
          v-for="transaction in transactions"
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
          v-if="transactions.length === 0"
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm"
            >No hay transacciones que coincidan con la búsqueda o los filtros actuales.</AppText
          >
        </div>

        <div
          ref="loadMoreSentinel"
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">{{ infiniteStatusLabel() }}</AppText>
          <div v-if="loadError && hasTransactions" class="mt-3 flex justify-center">
            <AppButton variant="secondary" @click="handleLoadMoreRetry">Reintentar</AppButton>
          </div>
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
        :is-loading-financial-goals="isLoadingFinancialGoals"
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
        :is-loading-financial-goals="isLoadingFinancialGoals"
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
