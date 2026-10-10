<script setup lang="ts">
import { computed, h, type VNode } from 'vue';
import {
    ChartAccessibility,
    ChartBar,
    ChartStacked,
    ChartSvg,
    ChartTooltip,
    ChartXAxis,
    ChartYAxis,
    type TickValue,
    type TooltipRenderContext,
} from '@primeui/vue-chart';

import { buildVirtualBalanceChart } from '@/modules/admin/lib/virtualBalanceChart';
import VirtualBalanceBreakdown from '@/modules/virtual-accounts/components/VirtualBalanceBreakdown.vue';
import type { VirtualAccountItem } from '@/modules/virtual-accounts/types';
import type { DashboardGraphAccount } from '@/modules/admin/types/dashboard';

const props = defineProps<{
    accounts: DashboardGraphAccount[];
    virtualAccounts?: VirtualAccountItem[];
}>();

const currencyOptions: Intl.NumberFormatOptions = {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
};
const currencyFormatter = new Intl.NumberFormat('es-MX', currencyOptions);
const colors = computed(() => props.accounts.map((account) => account.color ?? '#64748b'));
const virtualRows = computed(() =>
    buildVirtualBalanceChart(props.accounts, props.virtualAccounts ?? []),
);
const chartAccounts = computed(() => (props.virtualAccounts ? virtualRows.value : props.accounts));
const gainColors = computed(() =>
    virtualRows.value.map((account) =>
        account.gains < 0 ? 'var(--app-color-danger)' : 'var(--app-color-success)',
    ),
);
const hasUnclassified = computed(() =>
    virtualRows.value.some((account) => account.unclassified !== 0),
);
const hasNegativeSegments = computed(() =>
    virtualRows.value.some(
        (account) => account.savings < 0 || account.gains < 0 || account.unclassified < 0,
    ),
);
const chartKey = computed(
    () =>
        `${Boolean(props.virtualAccounts)}:${props.accounts.map((account) => account.accountId).join(',')}`,
);
const chartHeight = computed(() => Math.max(220, props.accounts.length * 36 + 48));

function accountLabel(value: TickValue): string {
    const account = props.accounts.find((item) => item.accountId === String(value));
    const label = account?.accountName ?? String(value);

    return label.length > 28 ? `${label.slice(0, 26)}…` : label;
}

function compactCurrency(value: TickValue): string {
    const amount = Number(value);

    if (Math.abs(amount) >= 1000) {
        return `$${(amount / 1000).toFixed(0)}k`;
    }

    return currencyFormatter.format(amount);
}

function renderTooltip(context: TooltipRenderContext): VNode {
    const account = chartAccounts.value[context.index];
    const breakdown = props.virtualAccounts ? virtualRows.value[context.index] : null;
    if (breakdown) {
        return h('div', { class: 'space-y-1' }, [
            h('p', { class: 'font-semibold' }, breakdown.accountName),
            h('p', `Total: ${currencyFormatter.format(breakdown.balance)}`),
            h('p', `Ahorro: ${currencyFormatter.format(breakdown.savings)}`),
            h('p', `Rendimiento: ${currencyFormatter.format(breakdown.gains)}`),
            ...(breakdown.unclassified !== 0
                ? [h('p', `Sin desglose: ${currencyFormatter.format(breakdown.unclassified)}`)]
                : []),
        ]);
    }

    return h('div', { class: 'space-y-1' }, [
        h('p', { class: 'font-semibold' }, account?.accountName ?? context.label),
        h('p', `Balance: ${currencyFormatter.format(context.value)}`),
    ]);
}
</script>

<template>
    <div class="w-full min-w-0">
        <ChartSvg
            :key="chartKey"
            :height="chartHeight"
            locale="es-MX"
            :number-format="currencyOptions"
            class="w-full"
        >
            <ChartStacked v-if="props.virtualAccounts" mode="normal" :gap="0">
                <ChartBar
                    :data="virtualRows"
                    category-y-field="accountId"
                    value-x-field="savings"
                    key-field="accountId"
                    name="Ahorro neto"
                    color="var(--app-color-primary)"
                    :max-bar-thickness="16"
                />
                <ChartBar
                    :data="virtualRows"
                    category-y-field="accountId"
                    value-x-field="gains"
                    key-field="accountId"
                    name="Rendimiento registrado"
                    :color="gainColors"
                    :max-bar-thickness="16"
                />
                <ChartBar
                    v-if="hasUnclassified"
                    :data="virtualRows"
                    category-y-field="accountId"
                    value-x-field="unclassified"
                    key-field="accountId"
                    name="Sin desglose"
                    color="var(--app-color-text-muted)"
                    :max-bar-thickness="16"
                />
            </ChartStacked>
            <ChartBar
                v-else
                :data="props.accounts"
                category-y-field="accountId"
                value-x-field="balance"
                key-field="accountId"
                name="Balance"
                :color="colors"
                :border-color="colors"
                :border-stroke-width="2"
                :max-bar-thickness="16"
            />
            <ChartXAxis :tick-format="compactCurrency" :tick-count="3" :grid-lines="true" />
            <ChartYAxis :tick-format="accountLabel" :grid-lines="false" />
            <ChartTooltip :render="renderTooltip" />
            <ChartAccessibility
                :description="
                    props.virtualAccounts
                        ? 'Balance por cuenta virtual, dividido en ahorro neto y rendimiento registrado, expresado en pesos mexicanos.'
                        : 'Balance por cuenta, expresado en pesos mexicanos.'
                "
            />
        </ChartSvg>
        <div
            v-if="props.virtualAccounts"
            class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm"
            aria-label="Segmentos de la barra"
        >
            <span class="flex items-center gap-2"
                ><span class="h-3 w-3 bg-(--app-color-primary)" aria-hidden="true" />Ahorro
                neto</span
            >
            <span class="flex items-center gap-2"
                ><span class="h-3 w-3 bg-(--app-color-success)" aria-hidden="true" />Rendimiento
                registrado</span
            >
            <span v-if="hasUnclassified" class="flex items-center gap-2"
                ><span class="h-3 w-3 bg-(--app-color-text-muted)" aria-hidden="true" />Sin
                desglose</span
            >
            <p v-if="hasNegativeSegments" class="w-full text-(--app-color-text-subtle)">
                Los valores negativos se muestran a la izquierda del cero; el total corresponde a la
                suma de los segmentos.
            </p>
        </div>
        <ul class="mt-4 divide-y divide-(--app-color-border)" aria-label="Balances por cuenta">
            <li
                v-for="(account, index) in chartAccounts"
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
                <VirtualBalanceBreakdown
                    v-if="props.virtualAccounts && virtualRows[index]"
                    class="mt-2 w-full"
                    :balance="{
                        currentBalance: account.balance,
                        netCapital: virtualRows[index]!.savings,
                        observedYield: virtualRows[index]!.gains,
                    }"
                />
            </li>
        </ul>
    </div>
</template>
