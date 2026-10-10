<script setup lang="ts">
import AppButton from './AppButton.vue';
import AppCard from './AppCard.vue';
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
    <AppCard
        :padded="false"
        class="border-dashed [&_.p-card-content]:px-4 [&_.p-card-content]:py-4 [&_.p-card-content]:text-center"
    >
        <AppText size="sm">{{ props.label }}</AppText>
        <div v-if="props.showRetry" class="mt-3 flex justify-center">
            <AppButton variant="secondary" @click="emit('retry')">{{ props.retryLabel }}</AppButton>
        </div>
    </AppCard>
</template>
