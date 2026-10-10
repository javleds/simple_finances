<script setup lang="ts">
import { CreditCardIcon } from '@heroicons/vue/24/outline';

import { AppCard, AppText } from '@/modules/shared/components';

defineProps<{
  plan: string;
  cycle: string;
  nextCharge: string;
  amount: string;
}>();
</script>

<template>
  <AppCard class="rounded-(--app-radius-control) sm:rounded-(--app-radius-control)">
    <div class="flex items-start gap-4">
      <div
        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-(--app-radius-control) bg-[color-mix(in_srgb,var(--app-color-primary)_12%,transparent)] text-(--app-color-primary)"
      >
        <CreditCardIcon class="h-6 w-6" />
      </div>

      <div class="min-w-0 flex-1 space-y-1">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-sm font-semibold text-(--app-color-text)">{{ plan }}</p>
            <AppText size="sm" tone="subtle">{{ cycle }}</AppText>
          </div>
          <p class="text-sm font-semibold text-(--app-color-text)">{{ amount }}</p>
        </div>

        <AppText size="sm">Próximo cargo: {{ nextCharge }}</AppText>
      </div>
    </div>
  </AppCard>
</template>
