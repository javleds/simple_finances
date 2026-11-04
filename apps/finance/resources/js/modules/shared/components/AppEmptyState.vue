<script setup lang="ts">
import AppCard from './AppCard.vue';
import AppText from './AppText.vue';

const props = defineProps<{
    message: string;
}>();
</script>

<template>
    <AppCard
        :padded="false"
        class="border-dashed [&_.p-card-content]:px-4 [&_.p-card-content]:py-4 [&_.p-card-content]:text-center"
    >
        <AppText size="sm">{{ props.message }}</AppText>
        <div v-if="$slots.action" class="mt-3 flex justify-center">
            <slot name="action" />
        </div>
    </AppCard>
</template>
