<script setup lang="ts">
import AppCard from './AppCard.vue';
import AppText from './AppText.vue';
import AppTitle from './AppTitle.vue';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
    }>(),
    {
        description: undefined,
    },
);
</script>

<template>
    <AppCard
        class="border-0! bg-transparent! p-0! shadow-none! sm:rounded-(--app-radius-control) sm:border! sm:bg-(--app-color-surface)! sm:p-8!"
    >
        <div class="space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1 space-y-1">
                    <AppTitle as="h2" size="sm">{{ props.title }}</AppTitle>
                    <AppText v-if="props.description">
                        {{ props.description }}
                    </AppText>
                </div>

                <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
                    <slot name="actions" />
                </div>
            </div>

            <div
                v-if="$slots['primary-metric']"
                class="py-2 sm:rounded-(--app-radius-control) sm:border sm:bg-(--app-color-surface-muted) sm:px-4 sm:py-4"
                :style="{ borderColor: 'var(--app-color-border)' }"
            >
                <slot name="primary-metric" />
            </div>

            <div
                v-if="$slots['secondary-metrics']"
                class="-mx-4 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0"
            >
                <div class="flex min-w-max items-stretch gap-2 lg:min-w-0 lg:flex-wrap lg:gap-4">
                    <slot name="secondary-metrics" />
                </div>
            </div>
        </div>
    </AppCard>
</template>
