<script setup lang="ts">
import { ArrowPathIcon } from '@heroicons/vue/24/outline';

import { AppCard, AppHeroMetric } from '@/modules/shared/components';

const props = defineProps<{
  balance: number;
}>();

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}
</script>

<template>
  <AppCard class="rounded-3xl">
    <div class="space-y-5">
      <AppHeroMetric label="Balance" :value="formatCurrency(props.balance)">
        <template #adornment>
          <div
            class="flex h-12 w-12 items-center justify-center rounded-full border bg-(--app-color-surface-muted)"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <ArrowPathIcon class="h-5 w-5 text-(--app-color-text-subtle)" />
          </div>
        </template>
      </AppHeroMetric>
    </div>
  </AppCard>
</template>
