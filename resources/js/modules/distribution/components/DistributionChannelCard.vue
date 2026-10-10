<script setup lang="ts">
import { ArrowsRightLeftIcon } from '@heroicons/vue/24/outline';

import { AppCard, AppText } from '@/modules/shared/components';

defineProps<{
  name: string;
  description: string;
  allocation: number;
  destination: string;
}>();
</script>

<template>
  <AppCard muted class="rounded-(--app-radius-control) sm:rounded-(--app-radius-control)">
    <div class="space-y-4">
      <div class="flex items-start gap-4">
        <div
          class="flex h-12 w-12 shrink-0 items-center justify-center rounded-(--app-radius-control) bg-[color-mix(in_srgb,var(--app-color-primary)_12%,transparent)] text-(--app-color-primary)"
        >
          <ArrowsRightLeftIcon class="h-6 w-6" />
        </div>

        <div class="min-w-0 flex-1">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-sm font-semibold text-(--app-color-text)">{{ name }}</p>
              <AppText size="sm">{{ description }}</AppText>
            </div>
            <p class="text-sm font-semibold text-(--app-color-text)">
              {{ allocation }}%
            </p>
          </div>
        </div>
      </div>

      <div class="space-y-2">
        <div class="h-2 rounded-(--app-radius-control) bg-(--app-color-surface)">
          <div
            class="h-2 rounded-(--app-radius-control) bg-(--app-color-primary)"
            :style="{ width: `${allocation}%` }"
          />
        </div>

        <AppText size="sm" tone="subtle">Destino: {{ destination }}</AppText>
      </div>
    </div>
  </AppCard>
</template>
