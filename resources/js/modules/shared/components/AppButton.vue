<script setup lang="ts">
import Button from 'primevue/button';
const props = withDefaults(
    defineProps<{
        type?: 'button' | 'submit' | 'reset';
        variant?: 'primary' | 'secondary' | 'ghost' | 'outline' | 'danger';
        fullWidth?: boolean;
        disabled?: boolean;
        loading?: boolean;
    }>(),
    {
        type: 'button',
        variant: 'primary',
        fullWidth: false,
        disabled: false,
        loading: false,
    },
);
</script>

<template>
    <Button
        :type="props.type"
        :disabled="props.disabled || props.loading"
        :aria-busy="props.loading"
        :severity="
            props.variant === 'danger'
                ? 'danger'
                : props.variant === 'secondary'
                  ? 'secondary'
                  : undefined
        "
        :text="props.variant === 'ghost'"
        :outlined="props.variant === 'outline'"
        :fluid="props.fullWidth"
        class="min-h-12 text-base"
    >
        <span
            v-if="props.loading"
            aria-hidden="true"
            class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent"
        />
        <slot />
    </Button>
</template>
