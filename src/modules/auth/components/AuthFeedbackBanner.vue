<script setup lang="ts">
import AppText from '@/modules/shared/components/AppText.vue';

const props = defineProps<{
  message: string;
  tone?: 'danger' | 'success' | 'info';
}>();

const toneClasses = {
  danger: {
    border: 'border-(--app-color-danger)',
    text: 'text-(--app-color-danger)!',
  },
  success: {
    border: 'border-(--app-color-success)',
    text: 'text-(--app-color-success)!',
  },
  info: {
    border: 'border-(--app-color-border-strong)',
    text: 'text-(--app-color-text)!',
  },
} as const;
</script>

<template>
  <section class="rounded-xl border px-4 py-3" :class="toneClasses[props.tone ?? 'danger'].border">
    <AppText size="sm" :class="toneClasses[props.tone ?? 'danger'].text">
      {{ props.message }}
    </AppText>
  </section>
</template>
