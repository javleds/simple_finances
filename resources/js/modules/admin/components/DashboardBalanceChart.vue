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
    <div class="min-h-[250px] w-full min-w-0">
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
        <ul
            class="mt-4 divide-y divide-(--app-color-border) sm:hidden"
            aria-label="Balances por cuenta"
        >
            <li
                v-for="account in props.accounts"
                :key="account.accountId"
                class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 py-2 text-sm"
            >
                <span class="flex min-w-0 flex-1 items-start gap-2">
                    <span
                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                        :style="{ backgroundColor: account.color ?? 'var(--app-color-text-muted)' }"
                        aria-hidden="true"
                    />
                    <span class="wrap-anywhere">{{ account.accountName }}</span>
                </span>
                <span class="font-semibold tabular-nums">{{
                    currencyFormatter.format(account.balance)
                }}</span>
            </li>
        </ul>
    </div>
</template>
