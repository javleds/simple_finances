<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    padded?: boolean;
    muted?: boolean;
  }>(),
  {
    padded: true,
    muted: false,
  },
);
</script>

<template>
  <div
    class="rounded-2xl border bg-(--app-color-surface) shadow-(--app-shadow-card)"
    :class="[props.padded ? 'p-6 sm:p-8' : '', props.muted ? 'bg-(--app-color-surface-muted)' : '']"
    :style="{ borderColor: 'var(--app-color-border)' }"
  >
    <slot />
  </div>
</template>
