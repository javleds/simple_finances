<script setup lang="ts">
import { CheckBadgeIcon, SparklesIcon } from '@heroicons/vue/24/outline';

import SubscriptionStatusCard from '@/modules/subscriptions/components/SubscriptionStatusCard.vue';
import { AppCard, AppText, AppTitle } from '@/modules/shared/components';

const subscriptions = [
  {
    plan: 'Plan Premium Facility',
    cycle: 'Facturación anual',
    nextCharge: '12 de mayo de 2026',
    amount: '$12,000',
  },
  {
    plan: 'Usuarios adicionales',
    cycle: 'Facturación mensual',
    nextCharge: '03 de mayo de 2026',
    amount: '$1,280',
  },
];
</script>

<template>
  <div class="space-y-5">
    <AppCard class="overflow-hidden !p-0">
      <div class="relative px-5 py-6 sm:px-6">
        <div
          class="absolute inset-x-0 top-0 h-24 bg-[linear-gradient(135deg,#0f766e,color-mix(in_srgb,#0f766e_52%,white))] opacity-95"
        />

        <div class="relative space-y-4">
          <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
              <AppText size="sm" tone="subtle" class="!text-white/80">
                Cobertura contratada
              </AppText>
              <AppTitle as="h2" size="sm" class="!text-white">
                Subscripciones activas
              </AppTitle>
            </div>

            <div
              class="rounded-2xl border border-white/20 bg-white/10 px-3 py-2 text-right backdrop-blur"
            >
              <p class="text-xs font-medium text-white/75">Estado</p>
              <p class="text-sm font-semibold text-white">Al día</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="rounded-3xl border border-white/15 bg-white/12 p-4">
              <SparklesIcon class="h-5 w-5 text-white" />
              <p class="mt-3 text-sm text-white/75">Módulos activos</p>
              <p class="mt-1 text-2xl font-semibold text-white">7</p>
            </div>
            <div class="rounded-3xl border border-white/15 bg-white/12 p-4">
              <CheckBadgeIcon class="h-5 w-5 text-white" />
              <p class="mt-3 text-sm text-white/75">Renovación</p>
              <p class="mt-1 text-2xl font-semibold text-white">Auto</p>
            </div>
          </div>
        </div>
      </div>
    </AppCard>

    <div class="space-y-1">
      <AppTitle as="h2" size="sm">Planes y cargos</AppTitle>
      <AppText>Detalle de las suscripciones vinculadas a esta organización.</AppText>
    </div>

    <section class="space-y-4">
      <SubscriptionStatusCard
        v-for="subscription in subscriptions"
        :key="subscription.plan"
        :plan="subscription.plan"
        :cycle="subscription.cycle"
        :next-charge="subscription.nextCharge"
        :amount="subscription.amount"
      />
    </section>
  </div>
</template>
