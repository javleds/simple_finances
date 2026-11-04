<script setup lang="ts">
import type { DashboardSubscriptionsSummary } from '@/modules/admin/types/dashboard';
import { AppCard, AppText, AppToggleButton, AppTitle } from '@/modules/shared/components';

type SavingsCadence = 'monthly' | 'biweekly';

const savingsCadence = defineModel<SavingsCadence>('savingsCadence', { required: true });

const props = defineProps<{
    annualSpend: number;
    recommendedSavings: number;
    summary: DashboardSubscriptionsSummary;
}>();

const cadenceOptions = [
    { value: 'monthly', label: 'Mensual' },
    { value: 'biweekly', label: 'Quincenal' },
] as const;

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatDateLabel(value: string): string {
    return new Intl.DateTimeFormat('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}
</script>

<template>
    <AppCard>
        <section class="space-y-4">
            <div class="space-y-3">
                <AppTitle as="h2" size="sm">Planeación de subscripciones</AppTitle>
                <AppToggleButton
                    :model-value="savingsCadence"
                    :options="cadenceOptions"
                    @update:model-value="savingsCadence = $event"
                />
            </div>

            <dl class="space-y-3">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <dt class="text-sm text-(--app-color-text-subtle)">Gasto anual</dt>
                    <dd
                        class="text-xl font-semibold wrap-anywhere text-(--app-color-text) tabular-nums"
                    >
                        {{ formatCurrency(props.annualSpend) }}
                    </dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <dt class="text-sm text-(--app-color-text-subtle)">
                        Ahorro {{ savingsCadence === 'monthly' ? 'mensual' : 'quincenal' }}
                    </dt>
                    <dd
                        class="text-xl font-semibold wrap-anywhere text-(--app-color-text) tabular-nums"
                    >
                        {{ formatCurrency(props.recommendedSavings) }}
                    </dd>
                </div>
            </dl>

            <div class="space-y-1 border-y border-(--app-color-border) py-4">
                <AppText size="sm" tone="subtle">Objetivo sano hoy</AppText>
                <p
                    class="text-[2rem] leading-[1.2] font-semibold wrap-anywhere text-(--app-color-text) tabular-nums"
                >
                    {{ formatCurrency(props.summary.savingsTargetToday) }}
                </p>
            </div>

            <dl class="space-y-3">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <dt class="text-sm text-(--app-color-text-subtle)">Próximos pagos</dt>
                    <dd
                        class="text-xl font-semibold wrap-anywhere text-(--app-color-text) tabular-nums"
                    >
                        {{ formatCurrency(props.summary.upcomingCommitment) }}
                    </dd>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <dt class="text-sm text-(--app-color-text-subtle)">Subs activas</dt>
                    <dd class="text-xl font-semibold text-(--app-color-text) tabular-nums">
                        {{ props.summary.subscriptionsCount }}
                    </dd>
                </div>
            </dl>

            <div
                v-if="props.summary.nearestPayment"
                class="space-y-1 border-t border-(--app-color-border) pt-4"
            >
                <AppText size="sm" tone="subtle">Próximo pago</AppText>
                <p class="text-base font-semibold wrap-anywhere text-(--app-color-text)">
                    {{ props.summary.nearestPayment.name }}
                </p>
                <p class="text-sm wrap-anywhere text-(--app-color-text-subtle) tabular-nums">
                    {{ formatCurrency(props.summary.nearestPayment.amount) }} ·
                    {{ formatDateLabel(props.summary.nearestPayment.nextPaymentDate) }}
                </p>
            </div>
        </section>
    </AppCard>
</template>
