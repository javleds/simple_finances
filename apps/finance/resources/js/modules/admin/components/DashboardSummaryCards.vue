<script setup lang="ts">
import type { DashboardAccountsSummary } from '@/modules/admin/types/dashboard';
import { AppCard, AppText } from '@/modules/shared/components';

const props = defineProps<{
    summary: DashboardAccountsSummary;
}>();
</script>

<template>
    <AppCard :padded="false">
        <section class="grid grid-cols-3 divide-x divide-(--app-color-border)">
            <div class="min-w-0 space-y-1 px-2 py-4 text-center">
                <AppText size="sm" tone="subtle">Cuentas activas</AppText>
                <p class="text-2xl font-semibold text-(--app-color-text) tabular-nums">
                    {{ props.summary.activeAccounts }}
                </p>
            </div>
            <div class="min-w-0 space-y-1 px-2 py-4 text-center">
                <AppText size="sm" tone="subtle">Cuentas virtuales</AppText>
                <p class="text-2xl font-semibold text-(--app-color-text) tabular-nums">
                    {{ props.summary.virtualAccounts }}
                </p>
            </div>
            <div class="min-w-0 space-y-1 px-2 py-4 text-center">
                <AppText size="sm" tone="subtle">Cuentas compartidas</AppText>
                <p class="text-2xl font-semibold text-(--app-color-text) tabular-nums">
                    {{ props.summary.sharedAccounts }}
                </p>
            </div>
        </section>
    </AppCard>
</template>
