<script setup lang="ts">
import type { TransactionFacilitySummary } from '@/modules/transactions/types';
import { AppCard, AppText, AppTitle } from '@/modules/shared/components';

const props = defineProps<{
  summary: TransactionFacilitySummary;
}>();

const currencyFormatter = new Intl.NumberFormat('es-MX', {
  style: 'currency',
  currency: 'MXN',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

function formatCurrency(value: number): string {
  return currencyFormatter.format(value);
}
</script>

<template>
  <AppCard class="rounded-3xl">
    <div class="space-y-4">
      <div class="space-y-1">
        <AppTitle as="h1" size="sm">Transacciones</AppTitle>
        <AppText>Movimientos completados creados por ti durante el periodo seleccionado.</AppText>
      </div>

      <AppCard
        class="border-0 bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-primary)_12%,transparent),transparent)] text-center"
      >
        <AppText size="sm" tone="subtle">Balance</AppText>
        <p class="mt-1 text-3xl font-semibold text-(--app-color-text)">
          {{ formatCurrency(props.summary.balance) }}
        </p>
      </AppCard>

      <div class="grid grid-cols-2 gap-3">
        <AppCard class="border-0 text-center">
          <AppText size="sm" tone="subtle">Ingresos</AppText>
          <p class="mt-1 text-xl font-semibold text-(--app-color-success)">
            {{ formatCurrency(props.summary.incomeTotal) }}
          </p>
        </AppCard>

        <AppCard class="border-0 text-center">
          <AppText size="sm" tone="subtle">Egresos</AppText>
          <p class="mt-1 text-xl font-semibold text-(--app-color-warning)">
            {{ formatCurrency(props.summary.outcomeTotal) }}
          </p>
        </AppCard>
      </div>
    </div>
  </AppCard>
</template>
