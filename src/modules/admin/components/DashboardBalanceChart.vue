<script setup lang="ts">
import { computed } from 'vue';
import { use } from 'echarts/core';
import { BarChart } from 'echarts/charts';
import { GridComponent, TooltipComponent } from 'echarts/components';
import { SVGRenderer } from 'echarts/renderers';
import VChart from 'vue-echarts';
import type { CallbackDataParams } from 'echarts/types/src/util/types.js';

import type { DashboardGraphAccount } from '@/modules/admin/types/dashboard';
import { useThemeStore } from '@/stores/theme';

use([BarChart, SVGRenderer, GridComponent, TooltipComponent]);

const props = defineProps<{
  accounts: DashboardGraphAccount[];
}>();

const themeStore = useThemeStore();
const chartInitOptions = { renderer: 'svg' as const };

const chartColors = computed(() => {
  themeStore.mode;

  if (typeof window === 'undefined') {
    return {
      surface: '#ffffff',
      border: '#dbe4f0',
      text: '#0f172a',
      textSubtle: '#64748b',
    };
  }

  const styles = window.getComputedStyle(document.documentElement);

  return {
    surface: styles.getPropertyValue('--app-color-surface').trim(),
    border: styles.getPropertyValue('--app-color-border').trim(),
    text: styles.getPropertyValue('--app-color-text').trim(),
    textSubtle: styles.getPropertyValue('--app-color-text-subtle').trim(),
  };
});

const balanceChartOption = computed(() => ({
  animationDuration: 350,
  grid: {
    left: 12,
    right: 12,
    top: 18,
    bottom: 36,
    containLabel: true,
  },
  tooltip: {
    trigger: 'axis',
    axisPointer: {
      type: 'shadow',
    },
    backgroundColor: chartColors.value.surface,
    borderColor: chartColors.value.border,
    borderWidth: 1,
    textStyle: {
      color: chartColors.value.text,
      fontFamily: 'inherit',
    },
    formatter: (params: CallbackDataParams | CallbackDataParams[]) => {
      const items = Array.isArray(params) ? params : [params];
      const firstItem = items[0];

      if (!firstItem || typeof firstItem !== 'object' || !('name' in firstItem)) {
        return '';
      }

      const value =
        typeof firstItem.value === 'number' ? firstItem.value : Number(firstItem.value ?? 0);

      return `
        <div style="min-width: 12rem;">
          <div style="font-weight: 600; margin-bottom: 0.25rem;">${String(firstItem.name)}</div>
          <div>Balance: ${formatCurrency(value)}</div>
        </div>
      `;
    },
  },
  xAxis: {
    type: 'category',
    data: props.accounts.map((account) => shortenLabel(account.accountName)),
    axisTick: {
      show: false,
    },
    axisLine: {
      lineStyle: {
        color: chartColors.value.border,
      },
    },
    axisLabel: {
      color: chartColors.value.textSubtle,
      fontSize: 11,
      interval: 0,
      rotate: 90,
    },
  },
  yAxis: {
    type: 'value',
    axisLine: {
      show: false,
    },
    splitLine: {
      lineStyle: {
        color: chartColors.value.border,
        opacity: 0.65,
      },
    },
    axisLabel: {
      color: chartColors.value.textSubtle,
      formatter: (value: number) => formatCompactCurrency(value),
    },
  },
  series: [
    {
      type: 'bar',
      barMaxWidth: 13,
      data: props.accounts.map((account) => ({
        value: account.balance,
        itemStyle: {
          color: 'transparent',
          borderColor: account.color ?? chartColors.value.textSubtle,
          borderWidth: 2,
          borderRadius: 0,
        },
      })),
    },
  ],
}));

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}

function formatCompactCurrency(value: number): string {
  if (value >= 100000) {
    return `$${Math.round(value / 1000)}k`;
  }

  if (value >= 1000) {
    return `$${(value / 1000).toFixed(0)}k`;
  }

  return formatCurrency(value);
}

function shortenLabel(label: string): string {
  if (label.length <= 14) {
    return label;
  }

  return `${label.slice(0, 12)}...`;
}
</script>

<template>
  <div class="h-[250px] min-h-[250px] w-full">
    <VChart
      :init-options="chartInitOptions"
      :option="balanceChartOption"
      autoresize
      class="block h-full w-full"
    />
  </div>
</template>
