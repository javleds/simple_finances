import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';
import { createCategoriesRepository } from '../repositories/categoriesRepository';
import type { Category, CategoryTransactionType } from '../types';

const repository = createCategoriesRepository();
export const categoryQueryKeys = { all: ['categories'] as const };

export function useCategories(
    accountId: MaybeRefOrGetter<string | null> = null,
    transactionType: MaybeRefOrGetter<Exclude<CategoryTransactionType, 'both'> | null> = null,
) {
    const client = useQueryClient();
    const isCreating = ref(false);
    const createError = ref<string | null>(null);
    const query = useQuery({
        queryKey: computed(() => [
            ...categoryQueryKeys.all,
            toValue(accountId),
            toValue(transactionType),
        ]),
        queryFn: () => repository.list(toValue(accountId), toValue(transactionType)),
    });

    watch(
        () => [toValue(accountId), toValue(transactionType)],
        () => {
            createError.value = null;
        },
    );

    async function createCategory(name: string): Promise<Category | null> {
        if (isCreating.value) return null;
        isCreating.value = true;
        createError.value = null;
        const catalogId = toValue(accountId);
        const type = toValue(transactionType);
        try {
            const category = await repository.create(catalogId, name, type ?? 'both');
            await client.invalidateQueries({ queryKey: categoryQueryKeys.all });
            return category;
        } catch (error) {
            const refreshed = await repository.list(catalogId).catch(() => null);
            if (catalogId !== toValue(accountId) || type !== toValue(transactionType)) return null;
            const normalizedName = name.trim().replace(/\s+/g, ' ').toLocaleLowerCase();
            const existing = refreshed?.categories.find(
                (category) => category.name.toLocaleLowerCase() === normalizedName,
            );
            if (
                existing &&
                (!type || existing.transactionType === 'both' || existing.transactionType === type)
            ) {
                await client.invalidateQueries({ queryKey: categoryQueryKeys.all });
                return existing;
            }
            if (existing) {
                createError.value =
                    'Ya existe una categoría con ese nombre para otro tipo de movimiento. Edita su aplicación en Categorías.';
                return null;
            }
            createError.value = resolveApiErrorMessage(error, 'No fue posible crear la categoría.');
            return null;
        } finally {
            isCreating.value = false;
        }
    }

    return {
        categories: computed(() => query.data.value?.categories ?? []),
        scope: computed(() => query.data.value?.scope ?? 'personal'),
        accountName: computed(() => query.data.value?.accountName ?? null),
        canManage: computed(() => query.data.value?.canManage ?? false),
        isLoading: query.isLoading,
        error: computed(() =>
            query.error.value
                ? resolveApiErrorMessage(query.error.value, 'No fue posible cargar las categorías.')
                : null,
        ),
        isCreating,
        createError,
        createCategory,
        refresh: query.refetch,
    };
}
