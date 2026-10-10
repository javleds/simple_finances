<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        as?: 'h1' | 'h2' | 'h3' | 'h4';
        size?: 'sm' | 'md' | 'lg';
    }>(),
    {
        as: 'h2',
        size: 'md',
    },
);

const sizeClasses = {
    sm: 'text-lg leading-6 sm:text-2xl',
    md: 'text-2xl leading-[1.875rem] sm:text-3xl',
    lg: 'text-3xl leading-[2.375rem] sm:text-4xl',
} as const;
</script>

<template>
    <component
        :is="props.as"
        class="font-semibold tracking-tight text-(--app-color-text)"
        :class="sizeClasses[props.size]"
    >
        <slot />
    </component>
</template>
