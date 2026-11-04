<script setup lang="ts">
import type { Component } from 'vue';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';

type ContextTabOption = {
    value: string;
    label: string;
    icon?: Component;
};

const props = withDefaults(
    defineProps<{
        modelValue: string;
        options: ReadonlyArray<ContextTabOption>;
        indicatorPosition?: 'top' | 'bottom';
    }>(),
    {
        indicatorPosition: 'bottom',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

function selectTab(value: string | number): void {
    if (props.modelValue !== String(value)) emit('update:modelValue', String(value));
}
</script>

<template>
    <Tabs :value="props.modelValue" scrollable @update:value="selectTab">
        <TabList
            :class="props.indicatorPosition === 'top' ? '[&_.p-tablist-active-bar]:top-0' : ''"
        >
            <Tab
                v-for="option in props.options"
                :key="option.value"
                :value="option.value"
                class="inline-flex flex-row items-center gap-2 whitespace-nowrap"
            >
                <component :is="option.icon" v-if="option.icon" class="h-4 w-4 shrink-0" />
                <span>{{ option.label }}</span>
            </Tab>
        </TabList>
    </Tabs>
</template>
