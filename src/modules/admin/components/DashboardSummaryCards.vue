<script setup lang="ts">
import type { DashboardAccountsSummary } from '@/modules/admin/types/dashboard';
import { AppCard, AppText } from '@/modules/shared/components';

const props = defineProps<{
  summary: DashboardAccountsSummary;
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
  <section class="grid gap-3 sm:grid-cols-3">
    <AppCard
      class="rounded-2xl bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-primary)_10%,transparent),transparent)] p-4!"
    >
      <div class="space-y-1">
        <AppText size="sm" tone="subtle">Cuentas activas</AppText>
        <p class="text-2xl font-semibold tracking-tight text-(--app-color-text)">
          {{ props.summary.activeAccounts }}
        </p>
      </div>
    </AppCard>

    <AppCard
      class="rounded-2xl bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-success)_10%,transparent),transparent)] p-4!"
    >
      <div class="space-y-1">
        <AppText size="sm" tone="subtle">Cuentas compartidas</AppText>
        <p class="text-2xl font-semibold tracking-tight text-(--app-color-text)">
          {{ props.summary.sharedAccounts }}
        </p>
      </div>
    </AppCard>

    <AppCard
      class="rounded-2xl bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-warning)_10%,transparent),transparent)] p-4!"
    >
      <div class="space-y-1">
        <AppText size="sm" tone="subtle">Por pagar</AppText>
        <p class="text-2xl font-semibold tracking-tight text-(--app-color-text)">
          {{ formatCurrency(props.summary.pendingTotal) }}
        </p>
      </div>
    </AppCard>
  </section>
</template>
