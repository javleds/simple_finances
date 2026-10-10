<script setup lang="ts">
import { formatSubscriptionFrequency } from '@/modules/subscriptions/schemas/subscriptionSchemas';
import SubscriptionListItem from '@/modules/subscriptions/components/SubscriptionListItem.vue';
import type { Subscription } from '@/modules/subscriptions/types';
import { AppEmptyState, AppText } from '@/modules/shared/components';

const props = defineProps<{
  subscriptions: Subscription[];
}>();

const emit = defineEmits<{
  delete: [subscriptionId: string];
  edit: [subscriptionId: string];
}>();

function formatDateLabel(date: string | null | undefined): string {
  if (!date) {
    return 'Sin fecha';
  }

  const normalizedDate = /^\d{4}-\d{2}-\d{2}$/.test(date) ? `${date}T00:00:00` : date;
  const parsedDate = new Date(normalizedDate);

  if (Number.isNaN(parsedDate.getTime())) {
    return 'Sin fecha';
  }

  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(parsedDate);
}
</script>

<template>
  <section class="space-y-3">
    <div class="flex items-center justify-between gap-3">
      <AppText size="sm" tone="subtle">
        {{ props.subscriptions.length }} suscripciones visibles
      </AppText>
      <AppText size="sm" tone="subtle">Scroll continuo</AppText>
    </div>

    <div class="space-y-4 lg:grid lg:grid-cols-2 lg:items-start lg:gap-4 lg:space-y-0">
      <SubscriptionListItem
        v-for="subscription in props.subscriptions"
        :key="subscription.id"
        :amount="subscription.amount"
        :cycle="formatSubscriptionFrequency(subscription.frequencyUnit, subscription.frequencyType)"
        :item-id="subscription.id"
        :next-charge="formatDateLabel(subscription.nextPaymentDate)"
        :plan="subscription.name"
        :status="subscription.finishedAt ? 'cancelled' : 'active'"
        @delete="emit('delete', $event)"
        @edit="emit('edit', $event)"
      />

      <AppEmptyState
        class="lg:col-span-full"
        v-if="props.subscriptions.length === 0"
        message="No hay suscripciones que coincidan con la búsqueda o los filtros actuales."
      />

      <div v-if="$slots.footer" class="lg:col-span-full"><slot name="footer" /></div>
    </div>
  </section>
</template>
