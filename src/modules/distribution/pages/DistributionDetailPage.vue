<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

import DistributionRelationListItem from '@/modules/distribution/components/DistributionRelationListItem.vue';
import { AppCard, AppText, AppTitle } from '@/modules/shared/components';

const route = useRoute();

const distributionRelations = {
  'daily-operations': {
    title: 'Operación diaria',
    description: 'Detalle de la relación principal de fondeo vinculada a esta regla.',
    relation: {
      concept: 'Transferencia recurrente a cuenta concentradora para operación inmediata',
      amount: 42250.5,
      type: 'transfer',
    },
  },
  'tax-reserve': {
    title: 'Reserva fiscal',
    description: 'Detalle del movimiento asociado a la reserva preventiva de obligaciones.',
    relation: {
      concept: 'Ahorro programado para obligaciones fiscales del siguiente corte',
      amount: 25000,
      type: 'saving',
    },
  },
  payroll: {
    title: 'Nómina',
    description: 'Detalle del movimiento de dispersión relacionado con pagos recurrentes.',
    relation: {
      concept: 'Transferencia programada para dispersión de nómina y compromisos periódicos',
      amount: 18000,
      type: 'transfer',
    },
  },
  'regional-buffer': {
    title: 'Bolsa regional de contingencia',
    description: 'Detalle del componente de ahorro reservado para imprevistos regionales.',
    relation: {
      concept: 'Ahorro preventivo para contingencias operativas en plaza',
      amount: 15000,
      type: 'saving',
    },
  },
} as const;

const distributionRule = computed(() => {
  const ruleId = typeof route.params.ruleId === 'string' ? route.params.ruleId : '';
  return distributionRelations[ruleId as keyof typeof distributionRelations] ?? null;
});
</script>

<template>
  <section v-if="distributionRule" class="space-y-4">
    <AppCard class="rounded-3xl">
      <div class="space-y-1">
        <AppTitle as="h2" size="sm">{{ distributionRule.title }}</AppTitle>
        <AppText>{{ distributionRule.description }}</AppText>
      </div>
    </AppCard>

    <section class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle">1 relación visible</AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <DistributionRelationListItem
          :amount="distributionRule.relation.amount"
          :concept="distributionRule.relation.concept"
          :type="distributionRule.relation.type"
        />

        <div
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">
            Sigue desplazándote para revisar relaciones adicionales conforme esta regla evolucione.
          </AppText>
        </div>
      </div>
    </section>
  </section>

  <AppCard v-else class="rounded-3xl">
    <div class="space-y-2">
      <AppTitle as="h2" size="sm">Regla no encontrada</AppTitle>
      <AppText>No encontramos una regla de distribución con ese identificador.</AppText>
    </div>
  </AppCard>
</template>
