<script setup lang="ts">
const props = defineProps<{
  name: string;
  value: string;
  helperText?: string;
}>();
</script>

<template>
  <div class="flex items-center gap-3 rounded-2xl bg-(--app-color-surface-muted) px-3 py-2.5">
    <div class="min-w-0 flex-1">
      <p class="truncate text-sm font-medium text-(--app-color-text)">
        {{ props.name }}
      </p>
      <p v-if="props.helperText" class="text-xs text-(--app-color-text-subtle)">
        {{ props.helperText }}
      </p>
    </div>

    <div class="flex shrink-0 items-center gap-2">
      <span class="text-sm font-semibold text-(--app-color-text)">
        {{ props.value }}
      </span>

      <slot name="action" />
    </div>
  </div>
</template>
