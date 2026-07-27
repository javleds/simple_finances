<script setup lang="ts">
import { ArrowPathIcon } from '@heroicons/vue/24/outline';

import { AppCard, AppHeroMetric } from '@/modules/shared/components';
import type { AccountMemberAmount } from '@/modules/accounts/types';

const props = defineProps<{
  balance: number;
  custodyByUser?: AccountMemberAmount[];
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

      <div v-if="props.custodyByUser?.length" class="border-t border-(--app-color-border) pt-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-(--app-color-text-subtle)">
          Custodia
        </p>
        <div class="mt-3 grid gap-2">
          <div
            v-for="custody in props.custodyByUser.filter((item) => item.amount > 0)"
            :key="custody.userId"
            class="flex items-center justify-between gap-3 text-sm"
          >
            <span class="min-w-0 truncate font-medium text-(--app-color-text)">
              {{ custody.userName }}
            </span>
            <span class="shrink-0 font-semibold text-(--app-color-text)">
              {{ formatCurrency(custody.amount) }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </AppCard>
</template>
