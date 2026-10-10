<script setup lang="ts">
import { ChevronRightIcon } from '@heroicons/vue/24/outline';

import { AppCard, AppText } from '@/modules/shared/components';

defineProps<{
  title: string;
  description: string;
}>();
</script>

<template>
  <AppCard class="rounded-2xl sm:rounded-3xl">
    <div class="flex items-center gap-4">
      <div class="min-w-0 flex-1 space-y-1">
        <p class="text-sm font-semibold text-(--app-color-text)">{{ title }}</p>
        <AppText size="sm">{{ description }}</AppText>
      </div>

      <ChevronRightIcon class="h-5 w-5 shrink-0 text-(--app-color-text-subtle)" />
    </div>
  </AppCard>
</template>
