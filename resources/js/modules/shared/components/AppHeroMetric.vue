<script setup lang="ts">
import AppText from './AppText.vue';

const props = defineProps<{
    label: string;
    value: string;
}>();
</script>

<template>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0 space-y-2">
            <AppText size="sm" tone="subtle">{{ props.label }}</AppText>
            <p
                class="text-[2rem] leading-[2.375rem] font-semibold tracking-tight break-all text-(--app-color-text) tabular-nums sm:text-4xl"
            >
                {{ props.value }}
            </p>
        </div>

        <div v-if="$slots.adornment" class="flex h-12 w-12 shrink-0 items-center justify-center">
            <slot name="adornment" />
        </div>
    </div>
</template>
