<script setup lang="ts">
import AppText from './AppText.vue';

const props = defineProps<{
    label: string;
    value: string;
    stacked?: boolean;
}>();
</script>

<template>
    <div
        :class="
            props.stacked
                ? 'grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2'
                : 'flex items-start justify-between gap-4'
        "
    >
        <div :class="props.stacked ? 'contents' : 'min-w-0 space-y-2'">
            <AppText size="sm" tone="subtle">{{ props.label }}</AppText>
            <p
                class="text-[2rem] leading-[2.375rem] font-semibold tracking-tight break-all text-(--app-color-text) tabular-nums sm:text-4xl"
                :class="
                    props.stacked
                        ? 'col-span-2 row-start-2 text-3xl! leading-tight! break-normal whitespace-nowrap'
                        : ''
                "
            >
                {{ props.value }}
            </p>
        </div>

        <div
            v-if="$slots.adornment"
            class="flex h-12 w-12 shrink-0 items-center justify-center"
            :class="props.stacked ? 'col-start-2 row-start-1' : ''"
        >
            <slot name="adornment" />
        </div>
    </div>
</template>
