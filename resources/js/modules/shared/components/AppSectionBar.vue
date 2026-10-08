<script setup lang="ts">
import AppText from './AppText.vue';
import AppTitle from './AppTitle.vue';

const props = defineProps<{
  title: string;
  description?: string;
}>();
</script>

<template>
  <section class="space-y-3">
    <div class="flex items-center justify-between gap-3">
      <div class="space-y-1">
        <AppTitle as="h2" size="sm">{{ props.title }}</AppTitle>
        <AppText v-if="props.description">{{ props.description }}</AppText>
      </div>

      <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
        <slot name="actions" />
      </div>
    </div>

    <div class="border-t" :style="{ borderColor: 'var(--app-color-border)' }"></div>
  </section>
</template>
