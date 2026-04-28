<script setup lang="ts">
import { ChevronRightIcon } from '@heroicons/vue/24/outline';
import { RouterLink } from 'vue-router';

import type { AccountRecord } from '@/modules/accounts/data/accounts';
import { AppCard } from '@/modules/shared/components';

const props = defineProps<{
  account: AccountRecord;
}>();

function statusClasses(status: AccountRecord['status']): string {
  if (status === 'Activo') {
    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
  }

  return 'bg-slate-500/10 text-slate-600 dark:text-slate-300';
}
</script>

<template>
  <RouterLink
    :to="{
      name: 'admin.accounts.view',
      params: { accountId: props.account.id },
    }"
    class="block"
  >
    <AppCard
      class="relative overflow-hidden rounded-xl !p-3.5 shadow-none transition hover:border-[var(--app-color-border-strong)]"
    >
      <div
        class="pointer-events-none absolute inset-y-0 left-0 w-24 opacity-90"
        :style="{
          background: `linear-gradient(90deg, color-mix(in srgb, ${props.account.color} 14%, transparent), transparent 78%)`,
        }"
      />

      <div class="relative grid grid-cols-[minmax(0,1fr)_auto_1rem] items-start gap-x-3 gap-y-2">
        <div class="min-w-0">
          <p
            class="overflow-hidden text-sm font-semibold leading-5 text-[var(--app-color-text)] [display:-webkit-box] [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
          >
            {{ props.account.name }}
          </p>
        </div>

        <span
          class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.04em]"
          :class="statusClasses(props.account.status)"
        >
          {{ props.account.status }}
        </span>

        <div class="row-span-2 flex items-center justify-end pt-0.5">
          <ChevronRightIcon class="h-4 w-4 shrink-0 text-[var(--app-color-text-subtle)]" />
        </div>

        <div class="col-span-2 flex min-w-0 items-center justify-between">
          <p
            class="text-[11px] font-medium uppercase tracking-[0.04em] text-[var(--app-color-text-subtle)]"
          >
            Balance
          </p>
          <p
            class="shrink-0 whitespace-nowrap text-sm font-semibold tabular-nums tracking-tight text-[var(--app-color-text)] sm:text-base"
          >
            {{ props.account.balance }}
          </p>
        </div>
      </div>
    </AppCard>
  </RouterLink>
</template>
