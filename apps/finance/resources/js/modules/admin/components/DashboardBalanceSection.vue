<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { createVirtualAccountsRepository } from '@/modules/virtual-accounts/repositories/virtualAccountsRepository';
import { dashboardQueryKeys } from '@/modules/admin/queries/dashboardQueries';

import type { DashboardGraphAccount } from '@/modules/admin/types/dashboard';
import {
    AppButton,
    AppCard,
    AppText,
    AppToggleButton,
    AppTitle,
} from '@/modules/shared/components';

type AccountGraphMode = 'physical' | 'virtual';

const DashboardBalanceChart = defineAsyncComponent(
    () => import('@/modules/admin/components/DashboardBalanceChart.vue'),
);

const graphMode = defineModel<AccountGraphMode>('graphMode', { required: true });

const props = defineProps<{
    accounts: DashboardGraphAccount[];
}>();

const virtualRepository = createVirtualAccountsRepository();
const virtualQuery = useQuery({
    queryKey: [...dashboardQueryKeys.all, 'virtual-balances'],
    queryFn: () => virtualRepository.loadDashboard(),
    enabled: computed(() => graphMode.value === 'virtual'),
    retry: false,
    staleTime: 0,
});

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

            <template v-if="graphMode === 'virtual'">
                <AppText v-if="virtualQuery.isPending.value"
                    >Cargando desglose de ahorro y rendimiento...</AppText
                >
                <div v-else-if="virtualQuery.isError.value" class="space-y-3">
                    <AppText>No fue posible cargar el desglose de cuentas virtuales.</AppText>
                    <AppButton variant="secondary" @click="virtualQuery.refetch()"
                        >Reintentar</AppButton
                    >
                </div>
                <DashboardBalanceChart
                    v-else
                    :accounts="props.accounts"
                    :virtual-accounts="virtualQuery.data.value?.accounts ?? []"
                />
            </template>
            <DashboardBalanceChart v-else :accounts="props.accounts" />
        </div>
    </AppCard>
</template>
