<script setup lang="ts">
import Select from 'primevue/select';
import Message from 'primevue/message';
import { computed, nextTick, ref, useAttrs, watch } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type SearchSelectOption = {
    value: string;
    label: string;
    description?: string;
};

const props = withDefaults(
    defineProps<{
        id: string;
        modelValue: string | null;
        options: ReadonlyArray<SearchSelectOption>;
        label?: string;
        placeholder?: string;
        searchPlaceholder?: string;
        emptyMessage?: string;
        disabled?: boolean;
        creatable?: boolean;
        creating?: boolean;
        openDirection?: 'top' | 'bottom';
        error?: string;
    }>(),
    {
        label: undefined,
        placeholder: 'Seleccionar opción',
        searchPlaceholder: 'Buscar opción',
        emptyMessage: 'No hay resultados disponibles.',
        disabled: false,
        creatable: false,
        creating: false,
        openDirection: 'top',
        error: undefined,
    },
);

const attrs = useAttrs();
const filterValue = ref('');
const selectionVersion = ref(0);
watch(
    () => props.creating,
    async (creating, previous) => {
        if (!creating && previous) {
            await nextTick();
            focusControl();
        }
    },
);
const createValue = '__app_create_option__';
const normalizedFilter = computed(() => filterValue.value.trim().replace(/\s+/g, ' '));
const selectOptions = computed(() => {
    const name = normalizedFilter.value;
    const exists = props.options.some(
        (option) =>
            option.label.trim().replace(/\s+/g, ' ').toLocaleLowerCase() ===
            name.toLocaleLowerCase(),
    );
    if (!props.creatable || !name || exists) return [...props.options];
    return [...props.options, { value: createValue, label: `Crear «${name}»` }];
});
const describedBy = computed(
    () =>
        [attrs['aria-describedby'], props.error ? `${props.id}-error` : undefined]
            .filter(Boolean)
            .join(' ') || undefined,
);
const required = computed(
    () => attrs.required === '' || attrs.required === true || attrs.required === 'true',
);

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
    change: [value: string | null];
    blur: [event: FocusEvent];
    create: [name: string];
}>();

function focusControl(): void {
    document.getElementById(props.id)?.focus();
}

function updateValue(nextValue: unknown): void {
    if (nextValue === createValue) {
        if (props.creatable && !props.creating) {
            selectionVersion.value += 1;
            emit('create', normalizedFilter.value);
        }
        return;
    }

    const normalizedValue = typeof nextValue === 'string' ? nextValue : null;

    emit('update:modelValue', normalizedValue);
    emit('change', normalizedValue);
}

function handleBlur(event: Event): void {
    emit('blur', event as FocusEvent);
}
</script>

<template>
    <div class="app-search-select space-y-2">
        <div v-if="props.label" class="flex min-h-5 items-center">
            <label
                :id="`${props.id}-label`"
                :for="props.id"
                @click="focusControl"
                class="text-sm font-medium"
                :class="props.error ? 'text-(--app-color-danger)' : 'text-(--app-color-label)'"
            >
                {{ props.label }}
            </label>
        </div>

        <Select
            :key="selectionVersion"
            :input-id="props.id"
            :model-value="props.modelValue"
            :options="selectOptions"
            option-value="value"
            option-label="label"
            filter
            :reset-filter-on-hide="props.creatable"
            show-clear
            fluid
            :filter-placeholder="props.searchPlaceholder"
            :placeholder="props.placeholder"
            :disabled="props.disabled || props.creating"
            :loading="props.creating"
            overlay-class="app-select-overlay"
            :empty-message="props.emptyMessage"
            :empty-filter-message="props.emptyMessage"
            :invalid="Boolean(props.error)"
            :aria-labelledby="props.label ? `${props.id}-label` : undefined"
            :pt="{
                label: { 'aria-describedby': describedBy, 'aria-required': required || undefined },
            }"
            class="min-h-12"
            v-bind="$attrs"
            @update:model-value="updateValue"
            @blur="handleBlur"
            @filter="filterValue = $event.value"
            @hide="filterValue = ''"
        >
            <template #option="{ option }">
                <div class="max-w-full min-w-0 space-y-0.5">
                    <p class="text-sm font-semibold wrap-anywhere">{{ option.label }}</p>
                    <p
                        v-if="option.description"
                        class="text-xs leading-5 wrap-anywhere text-(--app-color-text-subtle)"
                    >
                        {{ option.description }}
                    </p>
                </div>
            </template>
        </Select>
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
