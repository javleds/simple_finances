<script setup lang="ts" generic="TValue extends string">
import SelectButton from 'primevue/selectbutton';
type ToggleOption<TOptionValue extends string> = {
    value: TOptionValue;
    label: string;
    disabled?: boolean;
};

const props = defineProps<{
    modelValue: TValue;
    options: ReadonlyArray<ToggleOption<TValue>>;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: TValue];
}>();

function selectValue(value: TValue): void {
    if (!props.options.some((option) => option.value === value && !option.disabled)) return;
    emit('update:modelValue', value);
}
</script>

<template>
    <SelectButton
        :model-value="props.modelValue"
        :options="[...props.options]"
        option-label="label"
        option-value="value"
        option-disabled="disabled"
        :allow-empty="false"
        size="small"
        @update:model-value="selectValue"
    />
</template>
