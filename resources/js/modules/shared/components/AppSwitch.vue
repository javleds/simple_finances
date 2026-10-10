<script setup lang="ts">
import ToggleSwitch from 'primevue/toggleswitch';
const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();
</script>

<template>
    <ToggleSwitch
        :model-value="props.modelValue"
        :disabled="props.disabled"
        @update:model-value="emit('update:modelValue', Boolean($event))"
    />
</template>
