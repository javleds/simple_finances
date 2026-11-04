<script setup lang="ts" generic="T extends string">
const props = defineProps<{ label: string; options: readonly { value: T; label: string }[] }>();
const selected = defineModel<T[]>({ required: true });
</script>

<template>
    <fieldset class="m-0 min-w-0 border-0 p-0">
        <legend class="mb-3 text-sm font-semibold text-(--app-color-text)">
            {{ props.label }}
        </legend>
        <div class="grid grid-cols-2 gap-2">
            <label v-for="option in props.options" :key="option.value" class="filter-option">
                <input v-model="selected" type="checkbox" :value="option.value" />
                <span class="min-w-0 break-words">{{ option.label }}</span>
            </label>
        </div>
    </fieldset>
</template>

<style scoped>
@reference "../../../main.css";
.filter-option {
    @apply flex min-h-12 cursor-pointer items-center gap-3 rounded-(--app-radius-control) border border-(--app-color-border) bg-(--app-color-surface) px-3 py-3 text-sm font-medium text-(--app-color-text) transition;
}
.filter-option:has(input:checked) {
    border-color: var(--app-color-primary);
    background: color-mix(in srgb, var(--app-color-primary) 8%, var(--app-color-surface));
}
.filter-option:focus-within {
    outline: 2px solid var(--app-color-primary);
    outline-offset: 2px;
}
.filter-option input {
    width: 1.125rem;
    height: 1.125rem;
    flex-shrink: 0;
    accent-color: var(--app-color-primary);
}
</style>
