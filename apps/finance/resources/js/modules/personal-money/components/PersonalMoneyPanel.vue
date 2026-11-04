<script setup lang="ts">
import { computed } from 'vue';
import { ArrowUpRightIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import { AppButton, AppCard, AppLink, AppText, AppTitle } from '@/modules/shared/components';
import { usePersonalMoney } from '../composables/usePersonalMoney';

const { state, isLoading, loadError, retry } = usePersonalMoney();
const enabled = computed(() => state.value?.settings?.enabled === true);
const accountName = computed(
    () =>
        state.value?.accounts.find((account) => account.id === state.value?.settings?.account_id)
            ?.name,
);
const money = (value: number): string =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);
</script>

<template>
    <AppCard class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <AppTitle as="h2" size="sm">Mi dinero disponible</AppTitle>
            <LockClosedIcon
                class="h-4 w-4 shrink-0 text-(--app-color-text-subtle)"
                aria-label="Solo visible para ti"
            />
        </div>
        <AppText v-if="isLoading" role="status">Cargando tu disponible...</AppText>
        <div v-else-if="loadError" role="alert" class="flex flex-wrap items-center gap-2">
            <AppText>{{ loadError }}</AppText>
            <AppButton variant="ghost" @click="retry()">Reintentar</AppButton>
        </div>
        <template v-else-if="state">
            <div v-if="enabled" class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
                <div class="min-w-0 space-y-1">
                    <p
                        v-if="state.available_balance !== null"
                        aria-label="Saldo disponible"
                        class="text-3xl font-semibold tracking-tight wrap-anywhere tabular-nums"
                    >
                        {{ money(state.available_balance) }}
                    </p>
                    <AppText v-else role="alert"
                        >Configura otra cuenta individual para calcular tu disponible.</AppText
                    >
                    <AppText v-if="accountName" size="sm"
                        >{{ accountName }} · Saldo libre estimado</AppText
                    >
                </div>
                <AppLink
                    :to="{ name: 'admin.personal-money' }"
                    class="inline-flex min-h-11 items-center gap-2"
                    >Ver detalle <ArrowUpRightIcon class="h-4 w-4" aria-hidden="true"
                /></AppLink>
            </div>
            <div v-else class="flex flex-wrap items-center justify-between gap-2">
                <AppText
                    >Reúne tus gastos personales y compartidos en un saldo libre estimado.</AppText
                >
                <AppLink
                    :to="{ name: 'admin.personal-money' }"
                    class="inline-flex min-h-11 items-center"
                    >Configurar mi disponible</AppLink
                >
            </div>
            <AppLink
                v-if="enabled && state.pending_count > 0"
                :to="{ name: 'admin.personal-money', query: { resolution: 'pending' } }"
                class="inline-flex min-h-11 items-center text-sm"
                >{{ state.pending_count }} movimientos por revisar</AppLink
            >
        </template>
    </AppCard>
</template>
