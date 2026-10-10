<script setup lang="ts">
import { useRouter } from 'vue-router';

import type { DistributionFrequency } from '@/modules/distribution/types';
import { AppActionMenu, AppCard } from '@/modules/shared/components';

const props = defineProps<{
  itemId: string;
  name: string;
  frequency: DistributionFrequency;
  outcomesCount: number;
  totalAmount: number;
}>();

const emit = defineEmits<{
  edit: [itemId: string];
  delete: [itemId: string];
}>();

const router = useRouter();

const currencyFormatter = new Intl.NumberFormat('es-MX', {
  style: 'currency',
  currency: 'MXN',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

function frequencyLabel(frequency: DistributionFrequency): string {
  return frequency === 'semi_monthly' ? 'Quincenal' : 'Mensual';
}

function frequencyClasses(frequency: DistributionFrequency): string {
  if (frequency === 'semi_monthly') {
    return 'bg-[color-mix(in_srgb,var(--app-color-warning)_12%,transparent)] text-(--app-color-warning)';
  }

  return 'bg-[color-mix(in_srgb,var(--app-color-success)_10%,transparent)] text-(--app-color-success)';
}

function formattedAmount(amount: number): string {
  return currencyFormatter.format(amount);
}

function handleEdit(): void {
  emit('edit', props.itemId);
}

function handleDelete(): void {
  emit('delete', props.itemId);
}

function openRuleDetails(): void {
  router.push({
    name: 'admin.distribution.detail',
    params: { ruleId: props.itemId },
  });
}
</script>

<template>
  <AppCard
    class="relative cursor-pointer overflow-hidden rounded-2xl p-4! shadow-none transition hover:border-(--app-color-border-strong) sm:rounded-xl sm:p-3.5!"
    role="link"
    tabindex="0"
    @click="openRuleDetails"
    @keydown.enter="openRuleDetails"
    @keydown.space.prevent="openRuleDetails"
  >
    <div class="relative space-y-3">
      <div
        class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]"
      >
        <div class="min-w-0 space-y-1">
          <p class="text-sm leading-5 font-semibold break-words text-(--app-color-text)">
            {{ props.name }}
          </p>
          <p class="text-sm leading-5 text-(--app-color-text-muted)">
            {{ props.outcomesCount }} relaciones registradas
          </p>
        </div>

        <div
          class="col-start-1 row-start-2 space-y-1 sm:col-start-auto sm:row-start-auto sm:text-right"
        >
          <p
            class="text-sm font-semibold tracking-tight text-(--app-color-text) tabular-nums"
          >
            {{ formattedAmount(props.totalAmount) }}
          </p>
          <p
            class="text-[11px] font-medium tracking-[0.04em] text-(--app-color-text-subtle) uppercase"
          >
            Total
          </p>
        </div>

        <AppActionMenu
          class="col-start-2 row-start-1 shrink-0 sm:col-start-auto sm:row-start-auto"
          @delete="handleDelete"
          @edit="handleEdit"
        />
      </div>

      <div class="flex items-center gap-2">
        <span
          class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-[0.04em] uppercase"
          :class="frequencyClasses(props.frequency)"
        >
          {{ frequencyLabel(props.frequency) }}
        </span>
      </div>
    </div>
  </AppCard>
</template>
