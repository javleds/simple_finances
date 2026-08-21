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
    return 'bg-amber-500/12 text-amber-700 dark:text-amber-300';
  }

  return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
}

function accentStyle(frequency: DistributionFrequency): string {
  if (frequency === 'semi_monthly') {
    return 'linear-gradient(90deg, color-mix(in srgb, #f59e0b 16%, transparent), transparent 78%)';
  }

  return 'linear-gradient(90deg, color-mix(in srgb, var(--app-color-primary) 12%, transparent), transparent 78%)';
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
    class="relative cursor-pointer overflow-hidden rounded-xl p-3.5! shadow-none transition hover:border-(--app-color-border-strong)"
    role="link"
    tabindex="0"
    @click="openRuleDetails"
    @keydown.enter="openRuleDetails"
    @keydown.space.prevent="openRuleDetails"
  >
    <div
      class="pointer-events-none absolute inset-y-0 left-0 w-24 opacity-90"
      :style="{ background: accentStyle(props.frequency) }"
    />

    <div class="relative space-y-3">
      <div class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-start gap-3">
        <div class="min-w-0 space-y-1">
          <p
            class="[display:-webkit-box] overflow-hidden text-sm leading-5 font-semibold text-(--app-color-text) [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
          >
            {{ props.name }}
          </p>
          <p class="text-sm leading-5 text-(--app-color-text-muted)">
            {{ props.outcomesCount }} relaciones registradas
          </p>
        </div>

        <div class="space-y-1 text-right">
          <p class="text-sm font-semibold tracking-tight text-(--app-color-text) tabular-nums">
            {{ formattedAmount(props.totalAmount) }}
          </p>
          <p
            class="text-[11px] font-medium tracking-[0.04em] text-(--app-color-text-subtle) uppercase"
          >
            Total
          </p>
        </div>

        <AppActionMenu
          class="shrink-0"
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
