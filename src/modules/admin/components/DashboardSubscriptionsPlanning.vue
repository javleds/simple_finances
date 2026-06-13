<script setup lang="ts">
import { AppCard, AppText, AppToggleButton, AppTitle } from '@/modules/shared/components';

type SavingsCadence = 'monthly' | 'biweekly';

const savingsCadence = defineModel<SavingsCadence>('savingsCadence', { required: true });

const props = defineProps<{
  annualSpend: number;
  recommendedSavings: number;
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
</script>

<template>
  <div class="space-y-3">
    <AppCard class="rounded-3xl">
      <div class="flex flex-col items-center gap-4">
        <AppTitle as="h2" size="sm">Planeación de subscripciones</AppTitle>

        <AppToggleButton
          :model-value="savingsCadence"
          :options="cadenceOptions"
          @update:model-value="savingsCadence = $event as SavingsCadence"
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
    </AppCard>
  </div>
</template>
