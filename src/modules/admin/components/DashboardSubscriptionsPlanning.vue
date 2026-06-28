<script setup lang="ts">
import type { DashboardSubscriptionsSummary } from '@/modules/admin/types/dashboard';
import { AppCard, AppText, AppToggleButton, AppTitle } from '@/modules/shared/components';

type SavingsCadence = 'monthly' | 'biweekly';

const savingsCadence = defineModel<SavingsCadence>('savingsCadence', { required: true });

const props = defineProps<{
  annualSpend: number;
  recommendedSavings: number;
  summary: DashboardSubscriptionsSummary;
}>();

const cadenceOptions = [
  { value: 'monthly', label: 'Mensual' },
  { value: 'biweekly', label: 'Quincenal' },
] as const;

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}

function formatDateLabel(value: string): string {
  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(`${value}T00:00:00`));
}
</script>

<template>
  <div class="space-y-3">
    <AppCard class="rounded-3xl">
      <div class="flex flex-col items-center gap-4">
        <AppTitle as="h2" size="sm">Planeación de subscripciones</AppTitle>

        <AppToggleButton
          :model-value="savingsCadence"
          :options="cadenceOptions"
          @update:model-value="savingsCadence = $event"
        />
      </div>

      <section class="mt-3 grid grid-cols-2 gap-3">
        <AppCard class="mx-auto border-0 text-center">
          <div class="space-y-1">
            <AppText size="sm" tone="subtle">Gasto anual</AppText>
            <p class="text-2xl font-semibold tracking-tight text-(--app-color-text)">
              {{ formatCurrency(props.annualSpend) }}
            </p>
          </div>
        </AppCard>

        <AppCard class="mx-auto border-0 text-center">
          <div class="space-y-1">
            <AppText size="sm" tone="subtle">Ahorro </AppText>
            <p class="text-2xl font-semibold tracking-tight text-(--app-color-text)">
              {{ formatCurrency(props.recommendedSavings) }}
            </p>
          </div>
        </AppCard>
      </section>

      <section class="mt-3 space-y-3">
        <AppCard
          class="border-0 bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-primary)_12%,transparent),transparent)] text-center"
        >
          <div class="space-y-1">
            <AppText size="sm" tone="subtle">Objetivo sano hoy</AppText>
            <p class="text-3xl font-semibold tracking-tight text-(--app-color-text)">
              {{ formatCurrency(props.summary.savingsTargetToday) }}
            </p>
          </div>
        </AppCard>

        <div class="grid grid-cols-2 gap-3">
          <AppCard class="border-0 text-center">
            <div class="space-y-1">
              <AppText size="sm" tone="subtle">Próximos pagos</AppText>
              <p class="text-xl font-semibold tracking-tight text-(--app-color-text)">
                {{ formatCurrency(props.summary.upcomingCommitment) }}
              </p>
            </div>
          </AppCard>

          <AppCard class="border-0 text-center">
            <div class="space-y-1">
              <AppText size="sm" tone="subtle">Subs activas</AppText>
              <p class="text-xl font-semibold tracking-tight text-(--app-color-text)">
                {{ props.summary.subscriptionsCount }}
              </p>
            </div>
          </AppCard>
        </div>

        <AppCard v-if="props.summary.nearestPayment" class="border-0">
          <div class="space-y-1 text-center">
            <AppText size="sm" tone="subtle">Próximo pago</AppText>
            <p class="text-sm font-semibold text-(--app-color-text)">
              {{ props.summary.nearestPayment.name }}
            </p>
            <p class="text-sm text-(--app-color-text-subtle)">
              {{ formatCurrency(props.summary.nearestPayment.amount) }} ·
              {{ formatDateLabel(props.summary.nearestPayment.nextPaymentDate) }}
            </p>
          </div>
        </AppCard>
      </section>
    </AppCard>
  </div>
</template>
