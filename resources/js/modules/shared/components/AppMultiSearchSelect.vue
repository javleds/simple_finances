<script setup lang="ts">
import MultiSelect from 'primevue/multiselect';

const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        options: ReadonlyArray<{ value: string; label: string }>;
        placeholder?: string;
        searchPlaceholder?: string;
        loading?: boolean;
    }>(),
    {
        placeholder: 'Seleccionar opciones',
        searchPlaceholder: 'Buscar opción',
        loading: false,
    },
);
const selected = defineModel<string[]>({ required: true });

function updateSelection(value: unknown): void {
    selected.value = Array.isArray(value)
        ? value.filter((item): item is string => typeof item === 'string')
        : [];
}
</script>

<template>
    <div class="min-w-0 space-y-2">
        <label
            :id="`${props.id}-label`"
            :for="props.id"
            class="text-sm font-medium text-(--app-color-label)"
        >
            {{ props.label }}
        </label>
        <MultiSelect
            :inputId="props.id"
            :ariaLabelledby="`${props.id}-label`"
            :modelValue="selected"
            :options="[...props.options]"
            optionValue="value"
            optionLabel="label"
            filter
            showClear
            fluid
            :maxSelectedLabels="2"
            selectedItemsLabel="{0} seleccionadas"
            :placeholder="props.placeholder"
            :filterPlaceholder="props.searchPlaceholder"
            :loading="props.loading"
            :showToggleAll="false"
            overlayClass="app-select-overlay"
            class="min-h-12 min-w-0"
            @update:modelValue="updateSelection"
        />
    </div>
</template>
