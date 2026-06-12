<script setup lang="ts">
import { THEME_MODE, type ThemeMode } from '@/stores/theme';
import { AppCard, AppText, AppToggleButton } from '@/modules/shared/components';

const props = defineProps<{
  mode: ThemeMode;
}>();

const emit = defineEmits<{
  'update:mode': [mode: ThemeMode];
}>();

const themeOptions = [
  { value: THEME_MODE.LIGHT, label: 'Light' },
  { value: THEME_MODE.DARK, label: 'Dark' },
] as const;

function updateMode(nextMode: string): void {
  emit('update:mode', nextMode as ThemeMode);
}
</script>

<template>
  <AppCard
    muted
    :padded="false"
    class="rounded-xl px-4 py-3"
    :style="{ borderColor: 'var(--app-color-border-strong)' }"
  >
    <div class="flex items-center justify-between gap-4">
      <div class="space-y-1">
        <AppText as="div" tone="muted" class="font-medium text-(--app-color-text)">
          Tema visual
        </AppText>
        <AppText size="sm" tone="subtle"> Cambia entre light y dark mode. </AppText>
      </div>

      <AppToggleButton
        :model-value="props.mode"
        :options="themeOptions"
        @update:model-value="updateMode"
      />
    </div>
  </AppCard>
</template>
