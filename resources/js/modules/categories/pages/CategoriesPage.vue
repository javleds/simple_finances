<script setup lang="ts">
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed, ref, watch } from 'vue';
import Message from 'primevue/message';
import { createAccountsRepository } from '@/modules/accounts/repositories/accountsRepository';
import { transactionFacilityQueryKeys } from '@/modules/transactions/queries/transactionFacilityQueries';
import { accountQueryKeys } from '@/modules/accounts/queries/accountQueries';
import {
    AppActionMenu,
    AppButton,
    AppEmptyState,
    AppInput,
    AppListState,
    AppModal,
    AppSearchSelect,
    AppSectionHeader,
} from '@/modules/shared/components';
import { usePrimeForm } from '@/modules/shared/composables/usePrimeForm';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';
import { categoryQueryKeys, useCategories } from '../composables/useCategories';
import { createCategoriesRepository } from '../repositories/categoriesRepository';
import { categoryNameSchema } from '../schemas/categorySchemas';
import type { Category, CategoryDeletePayload } from '../types';

const repository = createCategoriesRepository();
const accountsRepository = createAccountsRepository();
const client = useQueryClient();
const selectedCatalog = ref<string | null>(null);
const search = ref('');
const categories = useCategories(selectedCatalog);
const accountsQuery = useQuery({
    queryKey: ['category-catalog-accounts'],
    queryFn: async () => {
        const accounts = [];
        let page = 1;
        while (true) {
            const result = await accountsRepository.list({ page, perPage: 100 });
            accounts.push(...result.items);
            if (!result.hasMore) return accounts;
            page += 1;
        }
    },
});
const catalogOptions = computed(() => [
    { value: '', label: 'Mis categorías', description: 'Disponibles en tus cuentas personales' },
    ...(accountsQuery.data.value ?? [])
        .filter((account) => account.usesSharedCategories)
        .map((account) => ({
            value: account.id,
            label: account.name,
            description: 'Categorías compartidas',
        })),
]);
const visibleCategories = computed(() =>
    categories.categories.value.filter((category) =>
        category.name.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase()),
    ),
);
const editing = ref<Category | null>(null);
const isFormOpen = ref(false);
const deleting = ref<Category | null>(null);
const deleteCatalogId = ref<string | null>(null);
const isPreparingDelete = ref(false);
const preparationError = ref<string | null>(null);
const deleteAction = ref<NonNullable<CategoryDeletePayload['action']> | null>(null);
const targetId = ref<string | null>(null);
const isSaving = ref(false);
const mutationError = ref<string | null>(null);
const nameForm = usePrimeForm({ schema: categoryNameSchema, initialValues: { name: '' } });
const name = computed({
    get: () => nameForm.values.name,
    set: (value: string) => nameForm.setFieldValue('name', value),
});
const targetOptions = computed(() =>
    categories.categories.value
        .filter((category) => category.id !== deleting.value?.id)
        .map((category) => ({ value: category.id, label: category.name })),
);
const canDelete = computed(
    () =>
        Boolean(deleting.value) &&
        !isSaving.value &&
        (deleting.value?.transactionsCount === 0 ||
            deleteAction.value === 'uncategorize' ||
            (deleteAction.value === 'reassign' && targetId.value)),
);

watch(selectedCatalog, () => {
    search.value = '';
    preparationError.value = null;
    deleting.value = null;
    isFormOpen.value = false;
});

function openForm(category: Category | null = null): void {
    if (categories.isLoading.value || categories.error.value || isSaving.value) return;
    editing.value = category;
    nameForm.resetForm({ values: { name: category?.name ?? '' } });
    mutationError.value = null;
    isFormOpen.value = true;
}

const saveCategory = nameForm.handleSubmit(async (): Promise<void> => {
    if (isSaving.value) return;
    isSaving.value = true;
    mutationError.value = null;
    try {
        if (editing.value) {
            await repository.update(selectedCatalog.value, editing.value.id, name.value.trim());
        } else {
            await repository.create(selectedCatalog.value, name.value.trim());
        }
        await invalidate();
        isFormOpen.value = false;
    } catch (error) {
        mutationError.value = resolveApiErrorMessage(error, 'No fue posible guardar la categoría.');
    } finally {
        isSaving.value = false;
    }
});

async function openDelete(category: Category): Promise<void> {
    if (isPreparingDelete.value || isSaving.value) return;
    const catalogId = selectedCatalog.value;
    isPreparingDelete.value = true;
    preparationError.value = null;
    mutationError.value = null;
    deleteAction.value = null;
    targetId.value = null;
    try {
        const result = await categories.refresh();
        if (selectedCatalog.value !== catalogId) return;
        if (result.error) {
            preparationError.value = resolveApiErrorMessage(
                result.error,
                'No fue posible verificar los movimientos de la categoría. Inténtalo de nuevo.',
            );
            return;
        }
        deleting.value = result.data?.categories.find((item) => item.id === category.id) ?? null;
        deleteCatalogId.value = catalogId;
    } catch (error) {
        if (selectedCatalog.value !== catalogId) return;
        preparationError.value = resolveApiErrorMessage(
            error,
            'No fue posible verificar los movimientos de la categoría. Inténtalo de nuevo.',
        );
    } finally {
        isPreparingDelete.value = false;
    }
}

async function invalidate(): Promise<void> {
    await Promise.all([
        client.invalidateQueries({ queryKey: categoryQueryKeys.all }),
        client.invalidateQueries({ queryKey: accountQueryKeys.all }),
        client.invalidateQueries({ queryKey: transactionFacilityQueryKeys.all }),
    ]);
}

async function deleteCategory(): Promise<void> {
    if (!canDelete.value || !deleting.value) return;
    isSaving.value = true;
    mutationError.value = null;
    try {
        await repository.remove(deleteCatalogId.value, deleting.value.id, {
            action: deleteAction.value ?? undefined,
            targetCategoryId: targetId.value,
        });
        await invalidate();
        deleting.value = null;
    } catch (error) {
        mutationError.value = resolveApiErrorMessage(
            error,
            'No fue posible eliminar la categoría.',
        );
        await categories.refresh();
        deleting.value =
            categories.categories.value.find((category) => category.id === deleting.value?.id) ??
            null;
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <div class="space-y-6">
        <AppSectionHeader
            title="Categorías"
            description="Organiza tus movimientos y los de tus cuentas compartidas."
        >
            <template #actions
                ><AppButton
                    :disabled="
                        categories.isLoading.value || Boolean(categories.error.value) || isSaving
                    "
                    @click="openForm()"
                    >Nueva categoría</AppButton
                ></template
            >
        </AppSectionHeader>
        <div class="grid gap-4 sm:grid-cols-2">
            <AppSearchSelect
                id="category-catalog"
                :model-value="selectedCatalog ?? ''"
                label="Catálogo"
                :disabled="isSaving"
                :options="catalogOptions"
                @update:model-value="selectedCatalog = $event || null"
            />
            <AppInput
                id="category-search"
                v-model="search"
                label="Buscar categorías"
                placeholder="Buscar por nombre"
                type="search"
            />
        </div>
        <Message v-if="accountsQuery.isError.value" severity="error"
            >No fue posible cargar los catálogos compartidos.
            <AppButton variant="ghost" @click="accountsQuery.refetch()"
                >Reintentar</AppButton
            ></Message
        >
        <Message v-if="preparationError" severity="error">{{ preparationError }}</Message>
        <p v-if="isPreparingDelete" role="status" class="text-sm">
            Verificando movimientos de la categoría…
        </p>
        <p class="text-sm text-(--app-color-text-subtle)">
            {{
                categories.scope.value === 'shared'
                    ? `Compartida · ${categories.accountName.value}. Los miembros pueden crear y usar categorías; el propietario puede editarlas y eliminarlas.`
                    : 'Personal · Estas categorías están disponibles en tus cuentas personales.'
            }}
        </p>
        <AppListState
            :is-loading="categories.isLoading.value"
            :has-items="categories.categories.value.length > 0"
            :error="categories.error.value"
            loading-label="Cargando categorías"
            @retry="categories.refresh()"
        >
            <AppEmptyState
                v-if="visibleCategories.length === 0"
                message="No hay categorías. Crea una o ajusta tu búsqueda."
            />
            <ul
                v-else
                class="divide-y divide-(--app-color-border) rounded-(--app-radius-control) border border-(--app-color-border)"
            >
                <li
                    v-for="category in visibleCategories"
                    :key="category.id"
                    class="flex items-center justify-between gap-3 p-4"
                >
                    <div class="min-w-0">
                        <p class="font-medium wrap-anywhere">{{ category.name }}</p>
                        <p class="text-sm text-(--app-color-text-subtle)">
                            {{ category.transactionsCount }} movimientos ·
                            {{ category.scope === 'shared' ? 'Compartida' : 'Personal' }}
                        </p>
                    </div>
                    <AppActionMenu
                        v-if="category.canManage"
                        @edit="openForm(category)"
                        @delete="openDelete(category)"
                    />
                </li>
            </ul>
        </AppListState>
        <AppModal
            :open="isFormOpen"
            :title="editing ? 'Editar categoría' : 'Nueva categoría'"
            :actions="[
                { key: 'cancel', label: 'Cancelar', autoClose: true },
                {
                    key: 'save',
                    label: 'Guardar',
                    tone: 'primary',
                    form: 'category-form',
                    type: 'submit',
                    loading: isSaving,
                    disabled: isSaving,
                },
            ]"
            @close="!isSaving && (isFormOpen = false)"
        >
            <form id="category-form" class="space-y-4" @submit.prevent="saveCategory">
                <Message v-if="mutationError" severity="error">{{ mutationError }}</Message>
                <AppInput
                    id="category-name"
                    v-model="name"
                    label="Nombre"
                    maxlength="100"
                    required
                    :error="nameForm.errors.value.name"
                />
                <p
                    v-if="categories.scope.value === 'shared'"
                    class="text-sm text-(--app-color-text-subtle)"
                >
                    Esta categoría estará disponible para todos los miembros de
                    {{ categories.accountName.value }}.
                </p>
            </form>
        </AppModal>
        <AppModal
            :open="Boolean(deleting)"
            title="Eliminar categoría"
            variant="danger"
            :actions="[
                { key: 'cancel', label: 'Cancelar', autoClose: true },
                {
                    key: 'delete',
                    label: 'Eliminar categoría',
                    tone: 'danger',
                    disabled: !canDelete,
                    loading: isSaving,
                },
            ]"
            @close="!isSaving && (deleting = null)"
            @action="$event === 'delete' && deleteCategory()"
        >
            <div class="space-y-4">
                <Message v-if="mutationError" severity="error">{{ mutationError }}</Message>
                <p>¿Eliminar «{{ deleting?.name }}»?</p>
                <p v-if="deleting?.scope === 'shared'" class="text-sm">
                    Categoría compartida de {{ categories.accountName.value }}. Esta decisión
                    también afecta movimientos registrados por otros miembros.
                </p>
                <template v-if="deleting && deleting.transactionsCount > 0">
                    <p>
                        {{ deleting.transactionsCount }} movimientos usan esta categoría. Elige qué
                        hacer con ellos:
                    </p>
                    <AppSearchSelect
                        id="category-delete-action"
                        v-model="deleteAction"
                        label="Qué hacer con los movimientos"
                        :options="[
                            { value: 'uncategorize', label: 'Dejar los movimientos sin categoría' },
                            ...(targetOptions.length
                                ? [{ value: 'reassign', label: 'Moverlos a otra categoría' }]
                                : []),
                        ]"
                    />
                    <AppSearchSelect
                        v-if="deleteAction === 'reassign'"
                        id="category-delete-target"
                        v-model="targetId"
                        label="Categoría de destino"
                        :options="targetOptions"
                    />
                </template>
                <p v-else>No hay movimientos que usen esta categoría.</p>
            </div>
        </AppModal>
    </div>
</template>
