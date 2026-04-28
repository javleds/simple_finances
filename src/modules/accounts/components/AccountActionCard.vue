<script setup lang="ts">
import { ChevronRightIcon } from '@heroicons/vue/24/outline';

import { AppCard, AppText } from '@/modules/shared/components';

defineProps<{
  title: string;
  description: string;
  icon: object;
}>();
</script>

<template>
  <AppCard class="rounded-3xl">
    <div class="flex items-center gap-4">
      <div
        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--app-color-primary)_12%,transparent)] text-[var(--app-color-primary)]"
      >
        <component :is="icon" class="h-6 w-6" />
      </div>

      <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-[var(--app-color-text)]">{{ title }}</p>
        <AppText size="sm">{{ description }}</AppText>
      </div>

      <ChevronRightIcon class="h-5 w-5 shrink-0 text-[var(--app-color-text-subtle)]" />
    </div>
  </AppCard>
</template>
