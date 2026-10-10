<script setup lang="ts">
import Button from 'primevue/button';
const props = withDefaults(
    defineProps<{
        type?: 'button' | 'submit' | 'reset';
        ariaLabel: string;
        disabled?: boolean;
        loading?: boolean;
    }>(),
    {
        type: 'button',
        disabled: false,
        loading: false,
    },
);
</script>

<template>
    <Button
        :type="props.type"
        :aria-label="props.ariaLabel"
        :disabled="props.disabled || props.loading"
        :aria-busy="props.loading ? 'true' : undefined"
        rounded
        outlined
        icon-only
        severity="secondary"
        class="h-11 w-11 shrink-0"
    >
        <span
            v-if="props.loading"
            aria-hidden="true"
            class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent"
        />
        <slot v-else />
    </Button>
</template>
