<script setup lang="ts">
import type { DashboardAccountsSummary } from '@/modules/admin/types/dashboard';
import { AppCard, AppText } from '@/modules/shared/components';

const props = defineProps<{
  summary: DashboardAccountsSummary;
}>();

</script>

<template>
  <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
    <AppCard
      class="rounded-2xl bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-primary)_10%,transparent),transparent)] p-4!"
    >
      <div class="space-y-1 text-center">
        <AppText size="sm" tone="subtle">Cuentas activas</AppText>
        <p class="text-2xl font-semibold tracking-tight text-(--app-color-text)">
          {{ props.summary.activeAccounts }}
        </p>
      </div>
    </AppCard>

    <AppCard
      class="rounded-2xl bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-success)_10%,transparent),transparent)] p-4!"
    >
      <div class="space-y-1 text-center">
        <AppText size="sm" tone="subtle">Cuentas virtuales</AppText>
        <p class="text-2xl font-semibold tracking-tight text-(--app-color-text)">
          {{ props.summary.virtualAccounts }}
        </p>
      </div>
    </AppCard>

    <AppCard
      class="rounded-2xl bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-success)_10%,transparent),transparent)] p-4!"
    >
      <div class="space-y-1">
        <AppText size="sm" tone="subtle">Cuentas compartidas</AppText>
        <p class="text-center text-2xl font-semibold tracking-tight text-(--app-color-text)">
          {{ props.summary.sharedAccounts }}
        </p>
      </div>
    </AppCard>
  </section>
</template>
