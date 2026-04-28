<script setup lang="ts">
import AppCard from '@/modules/shared/components/AppCard.vue';
import AppText from '@/modules/shared/components/AppText.vue';
import AppTitle from '@/modules/shared/components/AppTitle.vue';

const transactionItems = [
  {
    title: 'Pago a proveedor logístico',
    amount: '-$12,480',
    meta: 'Hoy · Conciliada',
  },
  {
    title: 'Dispersión interna desde facility',
    amount: '+$35,000',
    meta: 'Ayer · Entrada',
  },
  {
    title: 'Consumo operativo regional',
    amount: '-$4,860',
    meta: '22 Abr · Pendiente de revisión',
  },
];
</script>

<template>
  <section class="space-y-4">
    <AppCard class="rounded-3xl">
      <div class="space-y-1">
        <AppTitle as="h2" size="sm">Transacciones</AppTitle>
        <AppText>Vista embebida para revisar actividad y conciliación de la cuenta.</AppText>
      </div>
    </AppCard>

    <AppCard v-for="transaction in transactionItems" :key="transaction.title" class="rounded-3xl">
      <div class="flex items-start justify-between gap-4">
        <div class="space-y-1">
          <p class="text-sm font-semibold text-[var(--app-color-text)]">
            {{ transaction.title }}
          </p>
          <AppText size="sm">{{ transaction.meta }}</AppText>
        </div>
        <p class="shrink-0 text-sm font-semibold tabular-nums text-[var(--app-color-text)]">
          {{ transaction.amount }}
        </p>
      </div>
    </AppCard>
  </section>
</template>
