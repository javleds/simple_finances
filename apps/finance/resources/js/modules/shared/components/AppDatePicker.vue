<script setup lang="ts">
import DatePicker, { type DatePickerBlurEvent } from 'primevue/datepicker';
import Message from 'primevue/message';
import { computed } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<{
        id: string;
        modelValue?: string | null;
        label?: string;
        placeholder?: string;
        disabled?: boolean;
        clearable?: boolean;
        enableTimePicker?: boolean;
        error?: string;
        minDate?: Date | string | number;
        maxDate?: Date | string | number;
    }>(),
    {
        modelValue: null,
        label: undefined,
        placeholder: 'Seleccionar fecha',
        disabled: false,
        clearable: true,
        enableTimePicker: false,
        error: undefined,
        minDate: undefined,
        maxDate: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
    change: [value: string | null];
    blur: [event: FocusEvent];
}>();

function toLocalDate(value: Date | string | number | null | undefined): Date | undefined {
    if (value === null || value === undefined || value === '') return undefined;
    if (value instanceof Date) return value;
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [year, month, day] = value.split('-').map(Number);
        return new Date(year!, month! - 1, day!);
    }
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? undefined : date;
}

const dateValue = computed(() => toLocalDate(props.modelValue) ?? null);

function updateValue(value: Date | Date[] | (Date | null)[] | null | undefined): void {
    const date = value instanceof Date ? value : null;
    const normalizedValue =
        date && !Number.isNaN(date.getTime())
            ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
            : null;
    emit('update:modelValue', normalizedValue);
    emit('change', normalizedValue);
}

function handleBlur(event: DatePickerBlurEvent): void {
    emit('blur', event.originalEvent as FocusEvent);
}
</script>

<template>
    <div class="app-date-picker space-y-2">
        <div v-if="props.label" class="flex min-h-5 items-center">
            <label
                :for="props.id"
                class="text-sm font-medium"
                :class="props.error ? 'text-(--app-color-danger)' : 'text-(--app-color-label)'"
            >
                {{ props.label }}
            </label>
        </div>

        <DatePicker
            :input-id="props.id"
            :model-value="dateValue"
            date-format="yy-mm-dd"
            show-icon
            icon-display="input"
            :show-clear="props.clearable"
            :show-time="props.enableTimePicker"
            :placeholder="props.placeholder"
            :disabled="props.disabled"
            :min-date="toLocalDate(props.minDate)"
            :max-date="toLocalDate(props.maxDate)"
            :invalid="Boolean(props.error)"
            :aria-describedby="props.error ? `${props.id}-error` : undefined"
            fluid
            input-class="min-h-12"
            v-bind="$attrs"
            @update:model-value="updateValue"
            @blur="handleBlur"
        />
        <Message
            v-if="props.error"
            :id="`${props.id}-error`"
            severity="error"
            variant="simple"
            size="small"
        >
            {{ props.error }}
        </Message>
    </div>
</template>
