<script setup lang="ts">
import { computed, h, type VNode } from 'vue';
import {
    ChartAccessibility,
    ChartBar,
    ChartSvg,
    ChartTooltip,
    ChartXAxis,
    ChartYAxis,
    type TickValue,
    type TooltipRenderContext,
} from '@primeui/vue-chart';

import type { DashboardGraphAccount } from '@/modules/admin/types/dashboard';

const props = defineProps<{
    accounts: DashboardGraphAccount[];
}>();

const currencyOptions: Intl.NumberFormatOptions = {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
};
const currencyFormatter = new Intl.NumberFormat('es-MX', currencyOptions);
const colors = computed(() => props.accounts.map((account) => account.color ?? '#64748b'));

function accountLabel(value: TickValue): string {
    const account = props.accounts.find((item) => item.accountId === String(value));
    const label = account?.accountName ?? String(value);

    return label.length > 14 ? `${label.slice(0, 12)}...` : label;
}

function compactCurrency(value: TickValue): string {
    const amount = Number(value);

    if (amount >= 1000) {
        return `$${(amount / 1000).toFixed(0)}k`;
    }

    return currencyFormatter.format(amount);
}

function renderTooltip(context: TooltipRenderContext): VNode {
    const account = props.accounts[context.index];

    return h('div', { class: 'space-y-1' }, [
        h('p', { class: 'font-semibold' }, account?.accountName ?? context.label),
        h('p', `Balance: ${currencyFormatter.format(context.value)}`),
    ]);
}
</script>

<template>
    <div class="min-h-[250px] w-full">
        <ChartSvg :height="250" locale="es-MX" :number-format="currencyOptions" class="w-full">
            <ChartBar
                :data="props.accounts"
                category-x-field="accountId"
                value-y-field="balance"
                key-field="accountId"
                name="Balance"
                color="transparent"
                :border-color="colors"
                :border-stroke-width="2"
                :max-bar-thickness="13"
            />
            <ChartXAxis :tick-format="accountLabel" :tick-rotation="90" :grid-lines="false" />
            <ChartYAxis :tick-format="compactCurrency" :grid-lines="true" />
            <ChartTooltip :render="renderTooltip" />
            <ChartAccessibility description="Balance por cuenta, expresado en pesos mexicanos." />
        </ChartSvg>
    </div>
</template>
