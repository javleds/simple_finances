<script setup lang="ts">
import Message from 'primevue/message';
import { ArrowPathIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

import type { DashboardPeriodSummary } from '@/modules/admin/types/dashboard';
import {
    AppButton,
    AppCard,
    AppDatePicker,
    AppIconButton,
    AppLink,
    AppText,
    AppTitle,
} from '@/modules/shared/components';

const startDate = defineModel<string | null>('startDate', { required: true });
const endDate = defineModel<string | null>('endDate', { required: true });

const props = defineProps<{
    summary: DashboardPeriodSummary | null;
    isLoading: boolean;
    loadError: string | null;
    validationError: string | null;
}>();

const emit = defineEmits<{
    retry: [];
    resetPeriod: [];
}>();

const currencyFormatter = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function formatCurrency(value: number): string {
    return currencyFormatter.format(value);
}

const transactionsRoute = computed(() => ({
    name: 'admin.transactions',
    query: {
        start_date: startDate.value ?? undefined,
        end_date: endDate.value ?? undefined,
    },
}));
</script>

<template>
    <AppCard>
        <div class="space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <AppTitle as="h2" size="sm">Resumen del periodo</AppTitle>
                    <AppText>Ingresos y egresos registrados dentro del rango seleccionado.</AppText>
                </div>

                <AppIconButton ariaLabel="Volver al mes actual" @click="emit('resetPeriod')">
                    <ArrowPathIcon class="h-4 w-4" />
                </AppIconButton>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <AppDatePicker
                    id="dashboard-period-start-date"
                    v-model="startDate"
                    label="Desde"
                    :clearable="false"
                    :error="props.validationError ?? undefined"
                />

                <AppDatePicker
                    id="dashboard-period-end-date"
                    v-model="endDate"
                    label="Hasta"
                    :clearable="false"
                    :error="props.validationError ?? undefined"
                />
            </div>

            <div
                v-if="props.loadError"
                class="space-y-3 rounded-(--app-radius-control) border border-(--app-color-danger) px-4 py-3"
            >
                <Message severity="error">{{ props.loadError }}</Message>
                <AppButton variant="secondary" full-width @click="emit('retry')"
                    >Reintentar</AppButton
                >
            </div>

            <div
                v-else-if="props.isLoading && !props.summary"
                class="rounded-(--app-radius-control) border px-4 py-8 text-center"
                :style="{ borderColor: 'var(--app-color-border)' }"
            >
                <AppText>Cargando resumen...</AppText>
            </div>

            <section v-else class="space-y-4 border-t border-(--app-color-border) pt-4">
                <div class="space-y-1">
                    <AppText size="sm" tone="subtle">Balance</AppText>
                    <p
                        class="text-[2rem] leading-[1.2] font-semibold wrap-anywhere text-(--app-color-text) tabular-nums"
                    >
                        {{ formatCurrency(props.summary?.balance ?? 0) }}
                    </p>
                </div>
                <dl class="space-y-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <dt class="text-sm text-(--app-color-text-subtle)">Ingresos</dt>
                        <dd
                            class="text-xl font-semibold wrap-anywhere text-(--app-color-success) tabular-nums"
                        >
                            {{ formatCurrency(props.summary?.incomeTotal ?? 0) }}
                        </dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <dt class="text-sm text-(--app-color-text-subtle)">Egresos</dt>
                        <dd
                            class="text-xl font-semibold wrap-anywhere text-(--app-color-danger) tabular-nums"
                        >
                            {{ formatCurrency(props.summary?.outcomeTotal ?? 0) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <div class="flex justify-end">
                <AppLink :to="transactionsRoute" variant="secondary">Ver transacciones</AppLink>
            </div>
        </div>
    </AppCard>
</template>
