<script setup lang="ts">
import { ChevronRightIcon } from '@heroicons/vue/24/outline';

import { AppCard, AppText } from '@/modules/shared/components';

defineProps<{
    title: string;
    description: string;
    icon: object;
}>();
</script>

<template>
    <AppCard class="rounded-(--app-radius-control)">
        <div class="flex items-center gap-4">
            <div
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-(--app-radius-control) bg-[color-mix(in_srgb,var(--app-color-primary)_12%,transparent)] text-(--app-color-primary)"
            >
                <component :is="icon" class="h-6 w-6" />
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-(--app-color-text)">{{ title }}</p>
                <AppText size="sm">{{ description }}</AppText>
            </div>

            <ChevronRightIcon class="h-5 w-5 shrink-0 text-(--app-color-text-subtle)" />
        </div>
    </AppCard>
</template>
