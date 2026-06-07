<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  name: string;
  value: string;
  seed?: string;
  helperText?: string;
}>();

const initials = computed(() => {
  const words = props.name.trim().split(/\s+/).filter(Boolean);

  if (words.length === 0) {
    return '?';
  }

  const firstWord = words[0] ?? '';

  if (words.length === 1) {
    return firstWord.slice(0, 2).toUpperCase();
  }

  const secondWord = words[1] ?? '';

  return `${firstWord[0] ?? ''}${secondWord[0] ?? ''}`.toUpperCase();
});

const avatarStyle = computed<Record<string, string>>(() => {
  const seed = props.seed ?? props.name;
  const hue = Array.from(seed).reduce((accumulator, char) => accumulator + char.charCodeAt(0), 0) % 360;

  return {
    backgroundColor: `hsl(${hue} 70% 92%)`,
    color: `hsl(${hue} 48% 32%)`,
  };
});
</script>

<template>
  <div class="flex items-center gap-3 rounded-2xl bg-(--app-color-surface-muted) px-3 py-2.5">
    <div
      class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
      :style="avatarStyle"
    >
      {{ initials }}
    </div>

    <div class="min-w-0 flex-1">
      <p class="truncate text-sm font-medium text-(--app-color-text)">
        {{ props.name }}
      </p>
      <p v-if="props.helperText" class="text-xs text-(--app-color-text-subtle)">
        {{ props.helperText }}
      </p>
    </div>

    <span class="shrink-0 text-sm font-semibold text-(--app-color-text)">
      {{ props.value }}
    </span>
  </div>
</template>
