<script setup lang="ts">
import TransactionListItem from '@/modules/transactions/components/TransactionListItem.vue';
import type { Transaction } from '@/modules/transactions/types';
import { AppEmptyState, AppText } from '@/modules/shared/components';

const props = defineProps<{
  transactions: Transaction[];
}>();

function formatDateLabel(date: string): string {
  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(`${date}T00:00:00`));
}
</script>

<template>
  <section class="space-y-3">
    <div class="flex items-center justify-between gap-3">
      <AppText size="sm" tone="subtle">
        {{ props.transactions.length }} transacciones cargadas
      </AppText>
      <AppText size="sm" tone="subtle">Scroll continuo</AppText>
    </div>

    <div class="space-y-4">
      <TransactionListItem
        v-for="transaction in props.transactions"
        :key="transaction.id"
        :account-name="transaction.accountName"
        :amount="transaction.amount"
        :concept="transaction.concept"
        :creator-name="transaction.creatorName"
        :date-label="formatDateLabel(transaction.date)"
        :item-id="transaction.id"
        :meta-label="transaction.accountName ?? 'Cuenta no disponible'"
        :show-actions="false"
        :type="transaction.type"
      />

      <AppEmptyState
        v-if="props.transactions.length === 0"
        message="No hay transacciones completadas que coincidan con el periodo o la búsqueda."
      />

      <slot name="footer" />
    </div>
  </section>
</template>
