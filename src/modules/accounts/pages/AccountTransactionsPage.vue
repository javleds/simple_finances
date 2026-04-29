<script setup lang="ts">
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';

import { findAccountById } from '@/modules/accounts/data/accounts';
import {
  AppIconButton,
  AppButton,
  AppCard,
  AppInput,
  AppModal,
  AppText,
  AppTitle,
} from '@/modules/shared/components';
import TransactionsForm from '@/modules/transactions/components/TransactionsForm.vue';
import TransactionListItem from '@/modules/transactions/components/TransactionListItem.vue';
import { AdjustmentsHorizontalIcon, ArrowPathIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const transactionItems = [
  {
    concept: 'Pago a proveedor logístico con referencia operativa y validación de entrega regional',
    amount: 12480,
    type: 'expense',
    status: 'completed',
    dateLabel: 'Hoy',
  },
  {
    concept: 'Dispersión interna desde facility para reforzar la bolsa operativa del siguiente corte',
    amount: 35000,
    type: 'income',
    status: 'completed',
    dateLabel: 'Ayer',
  },
  {
    concept: 'Consumo operativo regional pendiente de conciliación con comprobantes de viaje',
    amount: 4860,
    type: 'expense',
    status: 'pending',
    dateLabel: '22 Abr',
  },
] as const;

const financialGoals = [
  {
    id: 'cashflow-monthly',
    name: 'Fondo operativo mensual',
    description: 'Cobertura del siguiente ciclo operativo y dispersión de pagos recurrentes.',
  },
  {
    id: 'tax-reserve',
    name: 'Reserva tributaria',
    description: 'Apartado para obligaciones fiscales y cierres programados.',
  },
  {
    id: 'regional-expansion',
    name: 'Expansión regional',
    description: 'Bolsa para ejecución local, traslados y despliegue de equipos.',
  },
] as const;

const route = useRoute();
const isCreateTransactionModalOpen = ref(false);
const isFiltersOpen = ref(false);
const searchTerm = ref('');
const selectedStatuses = ref<Array<'completed' | 'pending'>>([]);
const selectedTypes = ref<Array<'income' | 'expense'>>([]);

const transactionStatusOptions = [
  { value: 'completed', label: 'Completado' },
  { value: 'pending', label: 'Pendiente' },
] as const;

const transactionTypeOptions = [
  { value: 'income', label: 'Ingreso' },
  { value: 'expense', label: 'Egreso' },
] as const;

const account = computed(() => {
  const accountId = typeof route.params.accountId === 'string' ? route.params.accountId : '';
  return findAccountById(accountId);
});

const filteredTransactionItems = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return transactionItems.filter((transaction) => {
    const matchesQuery =
      normalizedQuery.length === 0 ||
      transaction.concept.toLowerCase().includes(normalizedQuery);

    if (!matchesQuery) {
      return false;
    }

    if (selectedStatuses.value.length > 0 && !selectedStatuses.value.includes(transaction.status)) {
      return false;
    }

    if (selectedTypes.value.length > 0 && !selectedTypes.value.includes(transaction.type)) {
      return false;
    }

    return true;
  });
});

function openCreateTransactionModal(): void {
  isCreateTransactionModalOpen.value = true;
}

function closeCreateTransactionModal(): void {
  isCreateTransactionModalOpen.value = false;
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

function handleTransactionSubmit(): void {
  closeCreateTransactionModal();
}
</script>

<template>
  <section class="space-y-4">
    <AppCard class="rounded-3xl">
      <div class="flex items-center justify-between gap-3">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Transacciones</AppTitle>
          <AppText>Vista embebida para revisar actividad y conciliación de la cuenta.</AppText>
        </div>

        <AppButton variant="primary" @click="openCreateTransactionModal">
          <PlusIcon class="h-4 w-4" />
        </AppButton>
      </div>
    </AppCard>

    <div class="flex items-center gap-3">
      <div class="relative flex-1">
        <div
          class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[var(--app-color-text-subtle)]"
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

    <section class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle">
          {{ filteredTransactionItems.length }} transacciones visibles
        </AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <TransactionListItem
          v-for="transaction in filteredTransactionItems"
          :key="`${transaction.concept}-${transaction.dateLabel}`"
          :amount="transaction.amount"
          :concept="transaction.concept"
          :date-label="transaction.dateLabel"
          :status="transaction.status"
          :type="transaction.type"
        />

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
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
            :class="
              selectedStatuses.includes(status.value)
                ? 'bg-[var(--app-color-primary)] text-[var(--app-color-primary-foreground)]'
                : 'bg-[var(--app-color-surface-muted)] text-[var(--app-color-text)]'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleStatus(status.value)"
          >
            {{ status.label }}
          </button>
        </div>

        <div class="space-y-2">
          <AppTitle as="h2" size="sm">Tipo</AppTitle>
          <AppText>Filtra entre ingresos y egresos sin salir del detalle de la cuenta.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="type in transactionTypeOptions"
            :key="type.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
            :class="
              selectedTypes.includes(type.value)
                ? 'bg-[var(--app-color-primary)] text-[var(--app-color-primary-foreground)]'
                : 'bg-[var(--app-color-surface-muted)] text-[var(--app-color-text)]'
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
      :actions="[
        { key: 'close', label: 'Cancelar', tone: 'danger', icon: XMarkIcon, autoClose: true },
        {
          key: 'submit-transaction',
          label: 'Guardar transacción',
          tone: 'primary',
          type: 'submit',
          form: 'transaction-form',
        },
      ]"
      title="Nueva transacción"
      variant="default"
      @close="closeCreateTransactionModal"
    >
      <TransactionsForm
        v-if="account"
        :account-users="account.users"
        :financial-goals="financialGoals"
        form-id="transaction-form"
        @submit="handleTransactionSubmit"
      />
    </AppModal>
  </section>
</template>
