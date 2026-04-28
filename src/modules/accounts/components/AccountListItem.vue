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
    <AppCard muted class="rounded-3xl transition hover:border-[var(--app-color-border-strong)]">
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-3">
          <span
            class="h-4 w-4 shrink-0 rounded-full border border-white/30 shadow-[0_0_0_4px_rgba(255,255,255,0.08)]"
            :style="{ backgroundColor: account.color }"
          />

          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-[var(--app-color-text)]">
              {{ account.name }}
            </p>
            <AppText size="sm" tone="subtle">{{ account.status }}</AppText>
          </div>
        </div>

        <div class="ml-auto flex items-center gap-3">
          <div class="text-right">
            <AppText size="sm" tone="subtle">Balance</AppText>
            <p class="text-lg font-semibold tracking-tight text-[var(--app-color-text)]">
              {{ account.balance }}
            </p>
          </div>

          <ChevronRightIcon class="h-5 w-5 shrink-0 text-[var(--app-color-text-subtle)]" />
        </div>
      </div>
    </AppCard>
  </RouterLink>
</template>
