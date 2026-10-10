<script setup lang="ts">
import { computed } from 'vue';
import { AppText } from '@/modules/shared/components';
import type { VirtualAccountSummary } from '@/modules/virtual-accounts/types';

const props = defineProps<{
    balance: Pick<VirtualAccountSummary, 'currentBalance' | 'netCapital' | 'observedYield'>;
}>();

const currencyFormatter = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const unclassifiedBalance = computed(
    () =>
        (Math.round(props.balance.currentBalance * 100) -
            Math.round(props.balance.netCapital * 100) -
            Math.round(props.balance.observedYield * 100)) /
        100,
);
const yieldClass = computed(() => {
    if (props.balance.observedYield > 0) return 'text-(--app-color-success)';
    if (props.balance.observedYield < 0) return 'text-(--app-color-danger)';
    return 'text-(--app-color-text-muted)';
});
const yieldLabel = computed(() => {
    const amount = currencyFormatter.format(props.balance.observedYield);
    return props.balance.observedYield > 0 ? `+${amount}` : amount;
});
</script>

<template>
    <div class="space-y-3">
        <dl class="grid grid-cols-2 gap-3">
            <div class="min-w-0">
                <dt class="text-sm text-(--app-color-text-subtle)">Ahorro neto</dt>
                <dd
                    class="mt-1 text-base font-semibold wrap-anywhere text-(--app-color-text) tabular-nums"
                >
                    {{ currencyFormatter.format(props.balance.netCapital) }}
                </dd>
            </div>
            <div class="min-w-0">
                <dt class="text-sm text-(--app-color-text-subtle)">Rendimiento registrado</dt>
                <dd
                    class="mt-1 text-base font-semibold wrap-anywhere tabular-nums"
                    :class="yieldClass"
                >
                    {{ yieldLabel }}
                </dd>
            </div>
        </dl>
        <AppText v-if="unclassifiedBalance !== 0" size="sm" tone="subtle">
            Saldo sin desglose: {{ currencyFormatter.format(unclassifiedBalance) }}. Esta diferencia
            del total aún no está clasificada como ahorro o rendimiento.
        </AppText>
    </div>
</template>
