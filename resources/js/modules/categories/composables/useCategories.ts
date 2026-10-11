import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed, ref, toValue, type MaybeRefOrGetter } from 'vue';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';
import { createCategoriesRepository } from '../repositories/categoriesRepository';
import type { Category } from '../types';

const repository = createCategoriesRepository();
export const categoryQueryKeys = { all: ['categories'] as const };

export function useCategories(accountId: MaybeRefOrGetter<string | null> = null) {
    const client = useQueryClient();
    const isCreating = ref(false);
    const createError = ref<string | null>(null);
    const query = useQuery({
        queryKey: computed(() => [...categoryQueryKeys.all, toValue(accountId)]),
        queryFn: () => repository.list(toValue(accountId)),
    });

    async function createCategory(name: string): Promise<Category | null> {
        if (isCreating.value) return null;
        isCreating.value = true;
        createError.value = null;
        try {
            const category = await repository.create(toValue(accountId), name);
            await client.invalidateQueries({ queryKey: categoryQueryKeys.all });
            return category;
        } catch (error) {
            await query.refetch();
            const normalizedName = name.trim().replace(/\s+/g, ' ').toLocaleLowerCase();
            const existing = query.data.value?.categories.find(
                (category) => category.name.toLocaleLowerCase() === normalizedName,
            );
            if (existing) return existing;
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
