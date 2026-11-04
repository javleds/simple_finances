<script setup lang="ts">
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import { computed, ref, useAttrs, watch } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type AppInputMask = 'none' | 'amount';
type AppInputMode = 'none' | 'text' | 'tel' | 'url' | 'email' | 'numeric' | 'decimal' | 'search';

const props = withDefaults(
    defineProps<{
        id: string;
        label?: string;
        type?: string;
        modelValue?: string | number | null;
        error?: string;
        mask?: AppInputMask;
    }>(),
    {
        label: undefined,
        type: 'text',
        modelValue: undefined,
        error: undefined,
        mask: 'none',
    },
);

const attrs = useAttrs();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    blur: [event: FocusEvent];
}>();

const isFocused = ref(false);
const editingValue = ref('');

const inputType = computed(() => {
    if (props.mask !== 'amount') {
        return props.type;
    }

    return 'text';
});

const inputMode = computed<AppInputMode | undefined>(() => {
    if (props.mask !== 'amount') {
        return undefined;
    }

    if (isAppInputMode(attrs.inputmode)) {
        return attrs.inputmode;
    }

    return 'decimal';
});

const inputAttrs = computed(() => {
    if (props.mask !== 'amount') {
        return attrs;
    }

    const { ...restAttrs } = attrs;

    return restAttrs;
});

const displayValue = computed(() => {
    if (props.mask !== 'amount') {
        return props.modelValue ?? '';
    }

    if (isFocused.value) {
        return editingValue.value;
    }

    return formatAmountDisplay(props.modelValue);
});

watch(
    () => props.modelValue,
    (nextValue) => {
        if (props.mask !== 'amount' || isFocused.value) {
            return;
        }

        editingValue.value = normalizeAmountValue(nextValue, {
            preserveTrailingDecimal: false,
        });
    },
    { immediate: true },
);

function handleInput(event: Event): void {
    const input = event.target as HTMLInputElement;

    if (props.mask !== 'amount') {
        emit('update:modelValue', input.value);
        return;
    }

    const normalizedValue = normalizeAmountValue(input.value, {
        preserveTrailingDecimal: true,
    });
    editingValue.value = normalizedValue;
    emit('update:modelValue', normalizedValue);
}

function handleFocus(): void {
    isFocused.value = true;

    if (props.mask !== 'amount') {
        return;
    }

    editingValue.value = normalizeAmountValue(props.modelValue, {
        preserveTrailingDecimal: false,
    });
}

function handleBlur(event: FocusEvent): void {
    if (props.mask === 'amount') {
        isFocused.value = false;
        const normalizedValue = normalizeAmountValue(editingValue.value, {
            preserveTrailingDecimal: false,
        });

        if (normalizedValue === '') {
            editingValue.value = '';
            emit('update:modelValue', '');
            emit('blur', event);
            return;
        }

        const fixedValue = toFixedAmount(normalizedValue);
        editingValue.value = fixedValue;
        emit('update:modelValue', fixedValue);
        emit('blur', event);
        return;
    }

    emit('blur', event);
}

function normalizeAmountValue(
    value: string | number | null | undefined,
    options: {
        preserveTrailingDecimal: boolean;
    },
): string {
    if (value === null || value === undefined) {
        return '';
    }

    const rawValue = String(value)
        .replace(',', '.')
        .replace(/[^0-9.]/g, '');

    if (rawValue === '') {
        return '';
    }

    const hasTrailingDecimal = rawValue.endsWith('.');
    const [integerPart = '', ...decimalParts] = rawValue.split('.');
    const normalizedInteger = normalizeIntegerPart(integerPart);
    const joinedDecimals = decimalParts.join('');
    const normalizedDecimals = joinedDecimals.slice(0, 2);

    if (rawValue.includes('.')) {
        if (normalizedDecimals === '' && hasTrailingDecimal && options.preserveTrailingDecimal) {
            return `${normalizedInteger}.`;
        }

        return `${normalizedInteger}.${normalizedDecimals}`;
    }

    return normalizedInteger;
}

function normalizeIntegerPart(value: string): string {
    const digitsOnly = value.replace(/\D/g, '');

    if (digitsOnly === '') {
        return '0';
    }

    return digitsOnly.replace(/^0+(?=\d)/, '');
}

function isAppInputMode(value: unknown): value is AppInputMode {
    return (
        value === 'none' ||
        value === 'text' ||
        value === 'tel' ||
        value === 'url' ||
        value === 'email' ||
        value === 'numeric' ||
        value === 'decimal' ||
        value === 'search'
    );
}

function formatAmountDisplay(value: string | number | null | undefined): string {
    const normalizedValue = normalizeAmountValue(value, {
        preserveTrailingDecimal: false,
    });

    if (normalizedValue === '') {
        return '';
    }

    const [integerPart, decimalPart = ''] = normalizedValue.split('.');
    const formattedInteger = Number(integerPart).toLocaleString('en-US');

    return `$ ${formattedInteger}.${decimalPart.padEnd(2, '0')}`;
}

function toFixedAmount(value: string): string {
    const normalizedValue = normalizeAmountValue(value, {
        preserveTrailingDecimal: false,
    });

    if (normalizedValue === '') {
        return '';
    }

    const [integerPart, decimalPart = ''] = normalizedValue.split('.');

    return `${integerPart}.${decimalPart.padEnd(2, '0')}`;
}
</script>

<template>
    <div class="space-y-2">
        <div v-if="props.label" class="flex min-h-5 items-center">
            <label
                :for="props.id"
                class="text-sm font-medium"
                :class="props.error ? 'text-(--app-color-danger)' : 'text-(--app-color-label)'"
            >
                {{ props.label }}
            </label>
        </div>
        <InputText
            :id="props.id"
            :type="inputType"
            :model-value="String(displayValue)"
            :inputmode="inputMode"
            :aria-invalid="Boolean(props.error)"
            :invalid="Boolean(props.error)"
            :aria-describedby="props.error ? `${props.id}-error` : undefined"
            class="min-h-12 w-full"
            v-bind="inputAttrs"
            @focus="handleFocus"
            @input="handleInput"
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
