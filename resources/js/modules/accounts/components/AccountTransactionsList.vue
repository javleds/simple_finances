<script setup lang="ts">
import TransactionListItem from '@/modules/transactions/components/TransactionListItem.vue';
import { canManageTransaction } from '@/modules/transactions/lib/transactionPermissions';
import type { Transaction } from '@/modules/transactions/types';
import { AppEmptyState, AppText } from '@/modules/shared/components';

const props = defineProps<{
  currentUserId: string | null;
  transactions: Transaction[];
}>();

const emit = defineEmits<{
  delete: [transactionId: string];
  edit: [transactionId: string];
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
        {{ props.transactions.length }} transacciones visibles
      </AppText>
      <AppText size="sm" tone="subtle">Scroll continuo</AppText>
    </div>

    <div class="space-y-2">
      <TransactionListItem
        v-for="transaction in props.transactions"
        :key="transaction.id"
        :amount="transaction.amount"
        :concept="transaction.concept"
        :creator-name="transaction.creatorName"
        :date-label="formatDateLabel(transaction.date)"
        :item-id="transaction.id"
        :pending-reimbursement-amount="transaction.currentUserPendingReimbursementAmount"
        :receivable-reimbursement-amount="transaction.currentUserReceivableReimbursementAmount"
        :show-actions="canManageTransaction(transaction, props.currentUserId)"
        :type="transaction.type"
        @delete="emit('delete', $event)"
        @edit="emit('edit', $event)"
      />

      <AppEmptyState
        v-if="props.transactions.length === 0"
        message="No hay transacciones que coincidan con la búsqueda o los filtros actuales."
      />

      <slot name="footer" />
    </div>
  </section>
</template>
