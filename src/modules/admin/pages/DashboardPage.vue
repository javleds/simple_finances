<script setup lang="ts">
import {
  ArrowTrendingUpIcon,
  BanknotesIcon,
  ChartBarIcon,
  CheckCircleIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline';

import { AppButton, AppCard, AppText, AppTitle } from '@/modules/shared/components';

type MetricCard = {
  title: string;
  value: string;
  caption: string;
  icon: typeof BanknotesIcon;
};

type DistributionItem = {
  name: string;
  percentage: number;
  amount: string;
};

const metricCards: MetricCard[] = [
  {
    title: 'Saldo disponible',
    value: '$248,320',
    caption: '+8.4% frente al cierre anterior',
    icon: BanknotesIcon,
  },
  {
    title: 'Ingresos del mes',
    value: '$92,400',
    caption: '17 depósitos conciliados hoy',
    icon: ArrowTrendingUpIcon,
  },
  {
    title: 'Cobertura activa',
    value: '96.2%',
    caption: 'Distribución en 12 cuentas',
    icon: ChartBarIcon,
  },
];

const distributionItems: DistributionItem[] = [
  { name: 'Cuenta operativa', percentage: 42, amount: '$104,294' },
  { name: 'Reserva fiscal', percentage: 25, amount: '$62,080' },
  { name: 'Nómina y pagos', percentage: 18, amount: '$44,697' },
  { name: 'Inversiones', percentage: 15, amount: '$37,249' },
];

const recentActivity = [
  {
    title: 'Distribución semanal ejecutada',
    detail: 'Se enviaron fondos a 4 cuentas objetivo sin incidencias.',
    status: 'Completado',
    icon: CheckCircleIcon,
  },
  {
    title: 'Nueva subscripción premium activada',
    detail: 'Plan anual aplicado para la organización principal.',
    status: 'Hace 2 horas',
    icon: ClockIcon,
  },
];
</script>

<template>
  <div class="space-y-5">
    <AppCard class="overflow-hidden !p-0">
      <div class="relative px-5 py-6 sm:px-6">
        <div
          class="absolute inset-x-0 top-0 h-24 bg-[linear-gradient(135deg,var(--app-color-primary),color-mix(in_srgb,var(--app-color-primary)_58%,white))] opacity-95"
        />
        <div class="relative space-y-4">
          <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
              <AppText size="sm" tone="subtle" class="!text-(--app-color-primary-foreground)/80">
                Resumen de hoy
              </AppText>
              <AppTitle as="h2" size="sm" class="!text-(--app-color-primary-foreground)">
                Tu escritorio financiero
              </AppTitle>
            </div>

            <div
              class="rounded-2xl border border-white/20 bg-white/10 px-3 py-2 text-right backdrop-blur"
            >
              <p class="text-xs font-medium text-(--app-color-primary-foreground)/75">
                Próximo corte
              </p>
              <p class="text-sm font-semibold text-(--app-color-primary-foreground)">Hoy 18:00</p>
            </div>
          </div>

          <div
            class="rounded-3xl border border-white/15 bg-(--app-color-surface)/96 p-4 shadow-[0_18px_45px_-30px_rgba(15,23,42,0.45)]"
          >
            <div class="flex items-end justify-between gap-4">
              <div>
                <AppText size="sm" tone="subtle">Balance consolidado</AppText>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-(--app-color-text)">
                  $481,912
                </p>
              </div>

              <span
                class="rounded-full bg-emerald-500/12 px-3 py-1 text-xs font-semibold text-emerald-600"
              >
                +12.8%
              </span>
            </div>
          </div>
        </div>
      </div>
    </AppCard>

    <section class="grid gap-4">
      <AppCard v-for="metric in metricCards" :key="metric.title" muted class="rounded-3xl">
        <div class="flex items-start gap-4">
          <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--app-color-primary)_12%,transparent)] text-(--app-color-primary)"
          >
            <component :is="metric.icon" class="h-6 w-6" />
          </div>

          <div class="min-w-0 space-y-1">
            <AppText size="sm" tone="subtle">{{ metric.title }}</AppText>
            <p class="text-2xl font-semibold tracking-tight text-(--app-color-text)">
              {{ metric.value }}
            </p>
            <AppText size="sm">{{ metric.caption }}</AppText>
          </div>
        </div>
      </AppCard>
    </section>

    <AppCard class="rounded-3xl">
      <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
          <div class="space-y-1">
            <AppTitle as="h2" size="sm">Distribución</AppTitle>
            <AppText>Así se reparte el capital disponible entre tus bolsillos activos.</AppText>
          </div>

          <AppButton variant="outline">Ajustar</AppButton>
        </div>

        <div class="space-y-4">
          <div v-for="item in distributionItems" :key="item.name" class="space-y-2">
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-(--app-color-text)">{{ item.name }}</p>
                <AppText size="sm" tone="subtle">{{ item.amount }}</AppText>
              </div>
              <p class="text-sm font-semibold text-(--app-color-text)">{{ item.percentage }}%</p>
            </div>

            <div class="h-2 rounded-full bg-(--app-color-surface-muted)">
              <div
                class="h-2 rounded-full bg-(--app-color-primary)"
                :style="{ width: `${item.percentage}%` }"
              />
            </div>
          </div>
        </div>
      </div>
    </AppCard>

    <AppCard class="rounded-3xl">
      <div class="space-y-4">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Actividad reciente</AppTitle>
          <AppText>Eventos importantes del escritorio en las últimas horas.</AppText>
        </div>

        <div class="space-y-3">
          <div
            v-for="activity in recentActivity"
            :key="activity.title"
            class="flex gap-3 rounded-2xl border bg-(--app-color-surface-muted) p-4"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <div
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-(--app-color-surface) text-(--app-color-primary)"
            >
              <component :is="activity.icon" class="h-5 w-5" />
            </div>

            <div class="min-w-0 space-y-1">
              <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-(--app-color-text)">
                  {{ activity.title }}
                </p>
                <span class="text-xs font-medium text-(--app-color-text-subtle)">
                  {{ activity.status }}
                </span>
              </div>
              <AppText size="sm">{{ activity.detail }}</AppText>
            </div>
          </div>
        </div>
      </div>
    </AppCard>
  </div>
</template>
