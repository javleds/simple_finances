<script setup lang="ts">
import AppButton from './AppButton.vue';
import AppText from './AppText.vue';

const props = withDefaults(
    defineProps<{
        label: string;
        showRetry?: boolean;
        retryLabel?: string;
    }>(),
    {
        retryLabel: 'Reintentar',
        showRetry: false,
    },
);

const emit = defineEmits<{
    retry: [];
}>();
</script>

<template>
    <div class="py-4 text-center" role="status" aria-live="polite">
        <AppText size="sm">{{ props.label }}</AppText>
        <div v-if="props.showRetry" class="mt-3 flex justify-center">
            <AppButton variant="secondary" @click="emit('retry')">{{ props.retryLabel }}</AppButton>
        </div>
    </div>
</template>
