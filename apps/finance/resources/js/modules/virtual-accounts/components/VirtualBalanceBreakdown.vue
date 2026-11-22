<script setup lang="ts">
import {
    subtractMoney,
    compareMoney,
    formatMoney as formatExactMoney,
} from '@/modules/shared/lib/money';
import { computed } from 'vue';
import { AppText } from '@/modules/shared/components';
import type { VirtualAccountSummary } from '@/modules/virtual-accounts/types';

const props = defineProps<{
    balance: Pick<VirtualAccountSummary, 'currentBalance' | 'netCapital' | 'observedYield'>;
}>();

const currencyFormatter = { format: formatExactMoney };
const unclassifiedBalance = computed(() =>
    subtractMoney(
        subtractMoney(props.balance.currentBalance, props.balance.netCapital),
        props.balance.observedYield,
    ),
);
const yieldClass = computed(() => {
    if (compareMoney(props.balance.observedYield, '0') > 0) return 'text-(--app-color-success)';
    if (compareMoney(props.balance.observedYield, '0') < 0) return 'text-(--app-color-danger)';
    return 'text-(--app-color-text-muted)';
});
const yieldLabel = computed(() => {
    const amount = currencyFormatter.format(props.balance.observedYield);
    return compareMoney(props.balance.observedYield, '0') > 0 ? `+${amount}` : amount;
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
        <AppText v-if="compareMoney(unclassifiedBalance, '0') !== 0" size="sm" tone="subtle">
            Saldo sin desglose: {{ currencyFormatter.format(unclassifiedBalance) }}. Esta diferencia
            del total aún no está clasificada como ahorro o rendimiento.
        </AppText>
    </div>
</template>
