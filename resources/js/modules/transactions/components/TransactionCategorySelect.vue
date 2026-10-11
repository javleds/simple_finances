<script setup lang="ts">
import { computed, watch } from 'vue';
import { useCategories } from '@/modules/categories/composables/useCategories';
import { AppButton, AppSearchSelect, AppText } from '@/modules/shared/components';

const props = defineProps<{
    accountId: string | null;
    modelValue: string | null;
}>();
const emit = defineEmits<{
    'update:modelValue': [value: string | null];
    busy: [value: boolean];
}>();
const {
    categories,
    scope,
    accountName,
    isLoading,
    error,
    isCreating,
    createError,
    createCategory,
    refresh,
} = useCategories(() => props.accountId);
const options = computed(() =>
    categories.value.map((category) => ({ value: category.id, label: category.name })),
);
const busy = computed(() => isCreating.value || isLoading.value);
watch(busy, (value) => emit('busy', value), { immediate: true });
watch(
    () => props.accountId,
    async (next, previous) => {
        if (next === previous) return;
        const result = await refresh();
        if (props.accountId !== next || result.error) return;
        if (
            props.modelValue &&
            !result.data?.categories.some((category) => category.id === props.modelValue)
        ) {
            emit('update:modelValue', null);
        }
    },
);

async function create(name: string): Promise<void> {
    const accountId = props.accountId;
    const category = await createCategory(name);
    if (category && props.accountId === accountId) emit('update:modelValue', category.id);
}
</script>

<template>
    <div class="space-y-2">
        <AppSearchSelect
            id="transaction-category"
            :model-value="props.modelValue"
            label="Categoría"
            :options="options"
            :disabled="isLoading || Boolean(error)"
            :creating="isCreating"
            creatable
            placeholder="Sin categoría"
            search-placeholder="Buscar o crear categoría"
            empty-message="Escribe un nombre para crear una categoría."
            :error="createError ?? error ?? undefined"
            @update:model-value="emit('update:modelValue', $event)"
            @create="create"
        />
        <AppText size="sm" tone="subtle">
            {{ scope === 'shared' ? `Compartida · ${accountName ?? 'Esta cuenta'}` : 'Personal' }}
            <span v-if="scope === 'shared'"
                >. Las categorías están disponibles para todos los miembros.</span
            >
        </AppText>
        <AppButton v-if="error" type="button" variant="ghost" @click="refresh()"
            >Reintentar</AppButton
        >
    </div>
</template>
