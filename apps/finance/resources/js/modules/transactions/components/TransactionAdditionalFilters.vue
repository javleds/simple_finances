<script setup lang="ts">
import { computed } from 'vue';
import {
    AppButton,
    AppMultiSearchSelect,
    AppSearchSelect,
    AppText,
} from '@/modules/shared/components';
import { useTransactionFilterOptions } from '../composables/useTransactionFilterOptions';
const props = defineProps<{ accountId?: string; accountScoped?: boolean }>();
const categories = defineModel<string[]>('categories', { required: true });
const hasAttachments = defineModel<boolean | null>('hasAttachments', { required: true });
const { query, options: availableOptions } = useTransactionFilterOptions(
    () => props.accountId,
    () => props.accountScoped === true,
);
const options = computed(() => [
    ...availableOptions.value,
    ...categories.value
        .filter((id) => !availableOptions.value.some((option) => option.value === id))
        .map((id) => ({ value: id, label: 'Categoría no disponible' })),
]);
const selection = computed({
    get: () => (hasAttachments.value === null ? 'all' : hasAttachments.value ? 'with' : 'without'),
    set: (value: string | null) => {
        hasAttachments.value = value === 'with' ? true : value === 'without' ? false : null;
    },
});
const attachmentOptions = [
    { value: 'all', label: 'Todos' },
    { value: 'with', label: 'Con comprobantes' },
    { value: 'without', label: 'Sin comprobantes' },
];
</script>
<template>
    <div class="grid gap-6">
        <AppText v-if="query.isPending.value" size="sm" role="status">Cargando categorías…</AppText>
        <div v-if="query.isError.value" class="space-y-2">
            <AppText size="sm" role="alert"
                >No fue posible cargar las categorías. Tus filtros se conservan.</AppText
            ><AppButton variant="ghost" @click="query.refetch()">Reintentar categorías</AppButton>
        </div>
        <AppMultiSearchSelect
            id="transaction-category-filter"
            v-model="categories"
            label="Categorías"
            :options="options"
            placeholder="Todas las categorías"
            search-placeholder="Buscar categoría"
            :loading="query.isFetching.value"
        />
        <AppSearchSelect
            id="transaction-attachment-filter"
            v-model="selection"
            label="Comprobantes"
            :options="attachmentOptions"
        />
    </div>
</template>
