<script setup lang="ts">
import { BanknotesIcon } from '@heroicons/vue/24/outline';

import { AppCard, AppText } from '@/modules/shared/components';

defineProps<{
  name: string;
  type: string;
  balance: string;
  status: string;
}>();
</script>

<template>
  <AppCard muted class="rounded-3xl">
    <div class="flex items-start gap-4">
      <div
        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--app-color-primary)_12%,transparent)] text-[var(--app-color-primary)]"
      >
        <BanknotesIcon class="h-6 w-6" />
      </div>

      <div class="min-w-0 flex-1 space-y-1">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-sm font-semibold text-[var(--app-color-text)]">{{ name }}</p>
            <AppText size="sm" tone="subtle">{{ type }}</AppText>
          </div>
          <span
            class="rounded-full bg-emerald-500/12 px-2.5 py-1 text-xs font-semibold text-emerald-600"
          >
            {{ status }}
          </span>
        </div>

        <p class="pt-1 text-2xl font-semibold tracking-tight text-[var(--app-color-text)]">
          {{ balance }}
        </p>
      </div>
    </div>
  </AppCard>
</template>
