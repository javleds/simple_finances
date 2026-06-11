<script setup lang="ts">
import AppText from './AppText.vue';

const props = defineProps<{
  message: string;
}>();
</script>

<template>
  <div
    class="rounded-2xl border border-dashed px-4 py-4 text-center"
    :style="{ borderColor: 'var(--app-color-border)' }"
  >
    <AppText size="sm">{{ props.message }}</AppText>
    <div v-if="$slots.action" class="mt-3 flex justify-center">
      <slot name="action" />
    </div>
  </div>
</template>
