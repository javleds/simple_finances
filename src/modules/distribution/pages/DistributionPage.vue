<script setup lang="ts">
import { FunnelIcon, PlayCircleIcon } from '@heroicons/vue/24/outline';

import DistributionChannelCard from '@/modules/distribution/components/DistributionChannelCard.vue';
import { AppButton, AppCard, AppText, AppTitle } from '@/modules/shared/components';

const channels = [
  {
    name: 'Operación diaria',
    description: 'Fondeo de la cuenta principal para ejecución inmediata.',
    allocation: 42,
    destination: 'Cuenta concentradora',
  },
  {
    name: 'Reserva fiscal',
    description: 'Separación preventiva para obligaciones del siguiente corte.',
    allocation: 25,
    destination: 'Reserva tributaria',
  },
  {
    name: 'Nómina',
    description: 'Distribución automática para pagos recurrentes.',
    allocation: 18,
    destination: 'Pagos y nómina',
  },
];
</script>

<template>
  <div class="space-y-5">
    <AppCard class="rounded-3xl">
      <div class="flex items-start justify-between gap-4">
        <div class="space-y-1">
          <AppText size="sm" tone="subtle">Motor de reglas</AppText>
          <AppTitle as="h2" size="sm">Distribución automática</AppTitle>
          <AppText>
            Define cómo se reparte el flujo disponible entre las cuentas destino.
          </AppText>
        </div>

        <div
          class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--app-color-primary)_12%,transparent)] text-[var(--app-color-primary)]"
        >
          <FunnelIcon class="h-6 w-6" />
        </div>
      </div>

      <div class="mt-5 flex items-center gap-3">
        <AppButton variant="primary">
          <PlayCircleIcon class="mr-2 h-4 w-4" />
          Ejecutar ahora
        </AppButton>
        <AppButton variant="outline">Editar reglas</AppButton>
      </div>
    </AppCard>

    <div class="space-y-1">
      <AppTitle as="h2" size="sm">Canales activos</AppTitle>
      <AppText>Cada regla determina prioridad, porcentaje y cuenta destino.</AppText>
    </div>

    <section class="space-y-4">
      <DistributionChannelCard
        v-for="channel in channels"
        :key="channel.name"
        :name="channel.name"
        :description="channel.description"
        :allocation="channel.allocation"
        :destination="channel.destination"
      />
    </section>
  </div>
</template>
