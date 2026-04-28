<script setup lang="ts">
import AppButton from '@/modules/shared/components/AppButton.vue';
import AppCard from '@/modules/shared/components/AppCard.vue';
import AppText from '@/modules/shared/components/AppText.vue';
import AppTitle from '@/modules/shared/components/AppTitle.vue';

const goalItems = [
  {
    title: 'Ahorro de reserva trimestral',
    detail: 'Avance de 68% sobre la meta objetivo.',
    meta: '$68,000 de $100,000',
    progress: 68,
  },
  {
    title: 'Fondo operativo mensual',
    detail: 'Objetivo para cubrir gastos recurrentes del siguiente ciclo.',
    meta: '$24,500 de $30,000',
    progress: 82,
  },
];
</script>

<template>
  <section class="space-y-4">
    <AppCard class="rounded-3xl">
      <div class="flex items-center justify-between gap-3">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Metas financieras</AppTitle>
          <AppText>Cada meta vive dentro de la cuenta y comparte su mismo contexto.</AppText>
        </div>
        <AppButton variant="outline">Crear meta</AppButton>
      </div>
    </AppCard>

    <AppCard v-for="goal in goalItems" :key="goal.title" class="rounded-3xl">
      <div class="space-y-3">
        <div class="space-y-1">
          <p class="text-sm font-semibold text-[var(--app-color-text)]">{{ goal.title }}</p>
          <AppText size="sm">{{ goal.detail }}</AppText>
        </div>

        <div class="space-y-2">
          <div class="flex items-center justify-between gap-3">
            <AppText size="sm" tone="subtle">Progreso</AppText>
            <p class="text-sm font-semibold text-[var(--app-color-text)]">{{ goal.meta }}</p>
          </div>
          <div class="h-2 rounded-full bg-[var(--app-color-surface-muted)]">
            <div
              class="h-2 rounded-full bg-[var(--app-color-primary)]"
              :style="{ width: `${goal.progress}%` }"
            />
          </div>
        </div>
      </div>
    </AppCard>
  </section>
</template>
