<script setup lang="ts">
import { computed, ref, useAttrs, watch } from 'vue';

defineOptions({
  inheritAttrs: false,
});

type AppInputMask = 'none' | 'amount';

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

const inputMode = computed(() => {
  if (props.mask !== 'amount') {
    return undefined;
  }

  return typeof attrs.inputmode === 'string' ? attrs.inputmode : 'decimal';
});

const inputAttrs = computed(() => {
  if (props.mask !== 'amount') {
    return attrs;
  }

  const {
    type: _type,
    inputmode: _inputmode,
    ...restAttrs
  } = attrs;

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

  const rawValue = String(value).replace(',', '.').replaceAll(/[^0-9.]/g, '');

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
  const digitsOnly = value.replaceAll(/\D/g, '');

  if (digitsOnly === '') {
    return '0';
  }

  return digitsOnly.replace(/^0+(?=\d)/, '');
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
  <div class="space-y-2.5">
    <div v-if="props.label" class="flex min-h-5 items-center">
      <label
        :for="props.id"
        class="text-sm font-medium"
        :class="props.error ? 'text-(--app-color-danger)' : 'text-(--app-color-label)'"
      >
        {{ props.label }}
      </label>
    </div>
    <input
      :id="props.id"
      :type="inputType"
      :value="displayValue"
      :inputmode="inputMode"
      :aria-invalid="Boolean(props.error)"
      class="h-12 w-full rounded-lg border bg-(--app-color-input-bg) px-4 text-sm text-(--app-color-input-text) transition outline-none placeholder:text-(--app-color-input-placeholder) focus:ring-4 focus:ring-(--app-color-focus-ring)"
      :class="
        props.error
          ? 'border-(--app-color-danger) focus:border-(--app-color-danger)'
          : 'border-(--app-color-input-border) focus:border-(--app-color-primary)'
      "
      v-bind="inputAttrs"
      @focus="handleFocus"
      @input="handleInput"
      @blur="handleBlur"
    />
    <p v-if="props.error" class="text-sm text-(--app-color-danger)">
      {{ props.error }}
    </p>
  </div>
</template>
