import { createApiClient } from '@/lib/api/apiClient';
import { categoryCatalogSchema, categoryResponseSchema } from '../schemas/categorySchemas';
import type { Category, CategoryCatalog, CategoryDeletePayload } from '../types';

const apiClient = createApiClient();

function catalogPath(accountId: string | null): string {
    return accountId ? `/accounts/${accountId}/categories` : '/categories';
}

export function createCategoriesRepository() {
    return {
        async list(accountId: string | null = null): Promise<CategoryCatalog> {
            return categoryCatalogSchema.parse(
                await apiClient.get<unknown>(catalogPath(accountId)),
            );
        },
        async create(accountId: string | null, name: string): Promise<Category> {
            return categoryResponseSchema.parse(
                await apiClient.post<unknown>(catalogPath(accountId), { name }),
            );
        },
        async update(accountId: string | null, id: string, name: string): Promise<Category> {
            return categoryResponseSchema.parse(
                await apiClient.put<unknown>(`${catalogPath(accountId)}/${id}`, { name }),
            );
        },
        async remove(
            accountId: string | null,
            id: string,
            payload: CategoryDeletePayload,
        ): Promise<void> {
            await apiClient.delete(`${catalogPath(accountId)}/${id}`, {
                body: {
                    action: payload.action,
                    target_category_id: payload.targetCategoryId ?? null,
                },
            });
        },
    };
}
