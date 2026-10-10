<script setup lang="ts">
import type { TransactionFacilitySummary } from '@/modules/transactions/types';
import { AppCard, AppText, AppTitle } from '@/modules/shared/components';

const props = defineProps<{
    summary: TransactionFacilitySummary;
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
</script>

<template>
    <section class="space-y-4">
        <div class="space-y-1">
            <AppTitle as="h1">Transacciones</AppTitle>
            <AppText
                >Movimientos completados creados por ti durante el periodo seleccionado.</AppText
            >
        </div>
        <AppCard>
            <div class="space-y-4">
                <div class="space-y-1">
                    <AppText size="sm" tone="subtle">Balance</AppText>
                    <p
                        class="text-[2rem] leading-[2.375rem] font-semibold wrap-anywhere text-(--app-color-text) tabular-nums"
                    >
                        {{ formatCurrency(props.summary.balance) }}
                    </p>
                </div>
                <dl class="space-y-3 border-t border-(--app-color-border) pt-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <dt class="text-sm text-(--app-color-text-subtle)">Ingresos</dt>
                        <dd
                            class="text-xl font-semibold wrap-anywhere text-(--app-color-success) tabular-nums"
                        >
                            {{ formatCurrency(props.summary.incomeTotal) }}
                        </dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <dt class="text-sm text-(--app-color-text-subtle)">Egresos</dt>
                        <dd
                            class="text-xl font-semibold wrap-anywhere text-(--app-color-danger) tabular-nums"
                        >
                            {{ formatCurrency(props.summary.outcomeTotal) }}
                        </dd>
                    </div>
                </dl>
            </div>
        </AppCard>
    </section>
</template>
