<script setup lang="ts">
import { defineAsyncComponent } from 'vue';

import type { DashboardGraphAccount } from '@/modules/admin/types/dashboard';
import { AppCard, AppText, AppToggleButton, AppTitle } from '@/modules/shared/components';

type AccountGraphMode = 'physical' | 'virtual';

const DashboardBalanceChart = defineAsyncComponent(
    () => import('@/modules/admin/components/DashboardBalanceChart.vue'),
);

const graphMode = defineModel<AccountGraphMode>('graphMode', { required: true });

const props = defineProps<{
    accounts: DashboardGraphAccount[];
}>();

const accountGraphModeOptions = [
    { value: 'physical', label: 'Físicas' },
    { value: 'virtual', label: 'Virtuales' },
] as const;
</script>

<template>
    <AppCard class="min-w-0">
        <div class="space-y-4">
            <div class="space-y-1">
                <AppTitle as="h2" size="sm">Balance por cuenta</AppTitle>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <AppText>
                        Vista comparativa para leer el balance actual de cada cuenta.
                    </AppText>

                    <AppToggleButton
                        class="self-start"
                        :model-value="graphMode"
                        :options="accountGraphModeOptions"
                        @update:model-value="graphMode = $event"
                    />
                </div>
            </div>

            <DashboardBalanceChart :accounts="props.accounts" />
        </div>
    </AppCard>
</template>
