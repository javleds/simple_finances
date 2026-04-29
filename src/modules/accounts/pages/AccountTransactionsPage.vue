<script setup lang="ts">
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';

import { findAccountById } from '@/modules/accounts/data/accounts';
import {
  AppButton,
  AppCard,
  AppModal,
  AppText,
  AppTitle,
} from '@/modules/shared/components';
import TransactionsForm from '@/modules/transactions/components/TransactionsForm.vue';
import TransactionListItem from '@/modules/transactions/components/TransactionListItem.vue';

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

const account = computed(() => {
  const accountId = typeof route.params.accountId === 'string' ? route.params.accountId : '';
  return findAccountById(accountId);
});

function openCreateTransactionModal(): void {
  isCreateTransactionModalOpen.value = true;
}

function closeCreateTransactionModal(): void {
  isCreateTransactionModalOpen.value = false;
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

    <TransactionListItem
      v-for="transaction in transactionItems"
      :key="`${transaction.concept}-${transaction.dateLabel}`"
      :amount="transaction.amount"
      :concept="transaction.concept"
      :date-label="transaction.dateLabel"
      :status="transaction.status"
      :type="transaction.type"
    />

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
