<script setup lang="ts">
import AppCard from './AppCard.vue';
import AppText from './AppText.vue';
import AppTitle from './AppTitle.vue';

const props = withDefaults(
  defineProps<{
    title: string;
    description?: string;
  }>(),
  {
    description: undefined,
  },
);
</script>

<template>
  <AppCard class="rounded-3xl">
    <div class="space-y-4">
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0 flex-1 space-y-1">
          <AppTitle as="h2" size="sm">{{ props.title }}</AppTitle>
          <AppText v-if="props.description">
            {{ props.description }}
          </AppText>
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
          <slot name="actions" />
        </div>
      </div>

      <div
        v-if="$slots['primary-metric']"
        class="rounded-2xl border bg-(--app-color-surface-muted) px-4 py-4"
        :style="{ borderColor: 'var(--app-color-border)' }"
      >
        <slot name="primary-metric" />
      </div>

      <div
        v-if="$slots['secondary-metrics']"
        class="-mx-6 overflow-x-auto px-6 pb-1 sm:mx-0 sm:px-0"
      >
        <div class="flex min-w-max items-stretch gap-2">
          <slot name="secondary-metrics" />
        </div>
      </div>
    </div>
  </AppCard>
</template>
