<script setup lang="ts">
import AppText from './AppText.vue';
import AppTitle from './AppTitle.vue';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        as?: 'h1' | 'h2' | 'h3';
    }>(),
    { as: 'h2' },
);
</script>

<template>
    <section class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0 flex-1 space-y-1">
                <AppTitle :as="props.as" :size="props.as === 'h1' ? 'md' : 'sm'">{{
                    props.title
                }}</AppTitle>
                <AppText v-if="props.description">{{ props.description }}</AppText>
            </div>

            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <div class="border-t" :style="{ borderColor: 'var(--app-color-border)' }"></div>
    </section>
</template>
