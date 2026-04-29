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

const transactionItems = [
  {
    title: 'Pago a proveedor logístico',
    amount: '-$12,480',
    meta: 'Hoy · Conciliada',
  },
  {
    title: 'Dispersión interna desde facility',
    amount: '+$35,000',
    meta: 'Ayer · Entrada',
  },
  {
    title: 'Consumo operativo regional',
    amount: '-$4,860',
    meta: '22 Abr · Pendiente de revisión',
  },
];

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

    <AppCard v-for="transaction in transactionItems" :key="transaction.title" class="rounded-3xl">
      <div class="flex items-start justify-between gap-4">
        <div class="space-y-1">
          <p class="text-sm font-semibold text-[var(--app-color-text)]">
            {{ transaction.title }}
          </p>
          <AppText size="sm">{{ transaction.meta }}</AppText>
        </div>
        <p class="shrink-0 text-sm font-semibold tabular-nums text-[var(--app-color-text)]">
          {{ transaction.amount }}
        </p>
      </div>
    </AppCard>

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
