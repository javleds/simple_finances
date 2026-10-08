<script setup lang="ts">
import AppButton from './AppButton.vue';
import AppText from './AppText.vue';

const props = withDefaults(
  defineProps<{
    isLoading: boolean;
    hasItems: boolean;
    error?: string | null;
    loadingLabel: string;
    retryLabel?: string;
  }>(),
  {
    error: null,
    retryLabel: 'Reintentar',
  },
);

const emit = defineEmits<{
  retry: [];
}>();
</script>

<template>
  <section
    v-if="props.isLoading && !props.hasItems"
    class="rounded-2xl border px-4 py-10 text-center"
    :style="{ borderColor: 'var(--app-color-border)' }"
  >
    <slot name="loading">
      <AppText>{{ props.loadingLabel }}</AppText>
    </slot>
  </section>

  <section
    v-else-if="props.error && !props.hasItems"
    class="space-y-3 rounded-2xl border px-4 py-6 text-center"
    :style="{ borderColor: 'var(--app-color-border)' }"
  >
    <slot name="error" :error="props.error">
      <AppText>{{ props.error }}</AppText>
      <div class="flex justify-center">
        <AppButton variant="secondary" @click="emit('retry')">{{ props.retryLabel }}</AppButton>
      </div>
    </slot>
  </section>

  <slot v-else />
</template>
