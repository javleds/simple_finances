<script setup lang="ts">
import Card from 'primevue/card';
const props = withDefaults(
    defineProps<{
        padded?: boolean;
        muted?: boolean;
    }>(),
    {
        padded: true,
        muted: false,
    },
);
</script>

<template>
    <Card
        class="rounded-(--app-radius-control) border border-(--app-color-border) shadow-(--app-shadow-card)"
        :class="[
            props.padded ? 'p-4 sm:p-8' : '',
            props.muted ? 'bg-(--app-color-surface-muted)!' : '',
        ]"
        :pt="{ body: { class: 'p-0!' } }"
    >
        <template #content><slot /></template>
    </Card>
</template>
