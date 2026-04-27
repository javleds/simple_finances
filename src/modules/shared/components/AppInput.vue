<script setup lang="ts">
defineOptions({
  inheritAttrs: false,
});

const props = withDefaults(
  defineProps<{
    id: string;
    label?: string;
    type?: string;
  }>(),
  {
    label: undefined,
    type: 'text',
  },
);
</script>

<template>
  <div class="space-y-2.5">
    <div v-if="props.label" class="flex min-h-5 items-center">
      <label :for="props.id" class="text-sm font-medium text-[var(--app-color-label)]">
        {{ props.label }}
      </label>
    </div>
    <input
      :id="props.id"
      :type="props.type"
      class="h-12 w-full rounded-lg border border-[var(--app-color-input-border)] bg-[var(--app-color-input-bg)] px-4 text-sm text-[var(--app-color-input-text)] outline-none placeholder:text-[var(--app-color-input-placeholder)] transition focus:border-[var(--app-color-primary)] focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
      v-bind="$attrs"
    />
  </div>
</template>
