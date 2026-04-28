<script setup lang="ts">
import { ChevronRightIcon } from '@heroicons/vue/24/outline';
import { RouterLink } from 'vue-router';

import type { AccountRecord } from '@/modules/accounts/data/accounts';
import { AppCard, AppText } from '@/modules/shared/components';

defineProps<{
  account: AccountRecord;
}>();
</script>

<template>
  <RouterLink :to="{ name: 'admin.accounts.detail', params: { accountId: account.id } }" class="block">
    <AppCard
      muted
      class="rounded-xl border-l-4 shadow-none transition hover:bg-[var(--app-color-surface)] hover:border-[var(--app-color-border-strong)]"
      :style="{ borderLeftColor: account.color }"
    >
      <div class="grid grid-cols-[minmax(0,1fr)_1.25rem] gap-x-3 gap-y-3 sm:gap-x-4">
        <div class="min-w-0">
          <div class="min-w-0 flex-1">
            <p
              class="overflow-hidden text-sm font-semibold leading-5 text-[var(--app-color-text)] [display:-webkit-box] [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
            >
              {{ account.name }}
            </p>
          </div>
        </div>

        <div class="row-span-2 flex items-center justify-end">
          <ChevronRightIcon class="h-5 w-5 shrink-0 text-[var(--app-color-text-subtle)]" />
        </div>

        <div class="flex min-w-0 items-end justify-between gap-3">
          <AppText size="sm" tone="subtle" class="truncate">{{ account.status }}</AppText>

          <div class="shrink-0 text-right">
            <AppText size="sm" tone="subtle">Balance</AppText>
            <p
              class="whitespace-nowrap text-base font-semibold tracking-tight tabular-nums text-[var(--app-color-text)] sm:text-lg"
            >
              {{ account.balance }}
            </p>
          </div>
        </div>
      </div>
    </AppCard>
  </RouterLink>
</template>
