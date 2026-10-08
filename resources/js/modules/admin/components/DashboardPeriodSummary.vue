<script setup lang="ts">
import { ArrowPathIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

import type { DashboardPeriodSummary } from '@/modules/admin/types/dashboard';
import {
  AppButton,
  AppCard,
  AppDatePicker,
  AppIconButton,
  AppLink,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

const startDate = defineModel<string | null>('startDate', { required: true });
const endDate = defineModel<string | null>('endDate', { required: true });

const props = defineProps<{
  summary: DashboardPeriodSummary | null;
  isLoading: boolean;
  loadError: string | null;
  validationError: string | null;
}>();

const emit = defineEmits<{
  retry: [];
  resetPeriod: [];
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

const transactionsRoute = computed(() => ({
  name: 'admin.transactions',
  query: {
    start_date: startDate.value ?? undefined,
    end_date: endDate.value ?? undefined,
  },
}));
</script>

<template>
  <AppCard class="rounded-3xl">
    <div class="space-y-4">
      <div class="flex items-start justify-between gap-3">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Resumen del periodo</AppTitle>
          <AppText>Ingresos y egresos registrados dentro del rango seleccionado.</AppText>
        </div>

        <AppIconButton ariaLabel="Volver al mes actual" @click="emit('resetPeriod')">
          <ArrowPathIcon class="h-4 w-4" />
        </AppIconButton>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <AppDatePicker
          id="dashboard-period-start-date"
          v-model="startDate"
          label="Desde"
          :clearable="false"
          :error="props.validationError ?? undefined"
        />

        <AppDatePicker
          id="dashboard-period-end-date"
          v-model="endDate"
          label="Hasta"
          :clearable="false"
          :error="props.validationError ?? undefined"
        />
      </div>

      <div
        v-if="props.loadError"
        class="space-y-3 rounded-2xl border border-(--app-color-danger) px-4 py-3"
      >
        <AppText class="text-(--app-color-danger)!">{{ props.loadError }}</AppText>
        <AppButton variant="secondary" full-width @click="emit('retry')">Reintentar</AppButton>
      </div>

      <div
        v-else-if="props.isLoading && !props.summary"
        class="rounded-2xl border px-4 py-8 text-center"
        :style="{ borderColor: 'var(--app-color-border)' }"
      >
        <AppText>Cargando resumen...</AppText>
      </div>

      <section v-else class="space-y-3">
        <AppCard
          class="border-0 bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-primary)_12%,transparent),transparent)] text-center"
        >
          <AppText size="sm" tone="subtle">Balance</AppText>
          <p class="mt-1 text-3xl font-semibold text-(--app-color-text)">
            {{ formatCurrency(props.summary?.balance ?? 0) }}
          </p>
        </AppCard>

        <div class="grid grid-cols-2 gap-3">
          <AppCard class="border-0 text-center">
            <AppText size="sm" tone="subtle">Ingresos</AppText>
            <p class="mt-1 text-xl font-semibold text-(--app-color-success)">
              {{ formatCurrency(props.summary?.incomeTotal ?? 0) }}
            </p>
          </AppCard>

          <AppCard class="border-0 text-center">
            <AppText size="sm" tone="subtle">Egresos</AppText>
            <p class="mt-1 text-xl font-semibold text-(--app-color-warning)">
              {{ formatCurrency(props.summary?.outcomeTotal ?? 0) }}
            </p>
          </AppCard>
        </div>
      </section>

      <div class="flex justify-end">
        <AppLink :to="transactionsRoute" variant="secondary">Ver transacciones</AppLink>
      </div>
    </div>
  </AppCard>
</template>
