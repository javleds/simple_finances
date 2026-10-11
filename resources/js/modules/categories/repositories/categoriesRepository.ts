import { createApiClient } from '@/lib/api/apiClient';
import { categoryCatalogSchema, categoryResponseSchema } from '../schemas/categorySchemas';
import type {
    Category,
    CategoryCatalog,
    CategoryDeletePayload,
    CategoryTransactionType,
} from '../types';

const apiClient = createApiClient();

function catalogPath(accountId: string | null): string {
    return accountId ? `/accounts/${accountId}/categories` : '/categories';
}

export function createCategoriesRepository() {
    return {
        async list(
            accountId: string | null = null,
            transactionType: Exclude<CategoryTransactionType, 'both'> | null = null,
        ): Promise<CategoryCatalog> {
            return categoryCatalogSchema.parse(
                await apiClient.get<unknown>(
                    `${catalogPath(accountId)}${transactionType ? `?transaction_type=${transactionType}` : ''}`,
                ),
            );
        },
        async create(
            accountId: string | null,
            name: string,
            transactionType: CategoryTransactionType = 'both',
        ): Promise<Category> {
            return categoryResponseSchema.parse(
                await apiClient.post<unknown>(catalogPath(accountId), {
                    name,
                    transaction_type: transactionType,
                }),
            );
        },
        async update(
            accountId: string | null,
            id: string,
            name: string,
            transactionType?: CategoryTransactionType,
        ): Promise<Category> {
            return categoryResponseSchema.parse(
                await apiClient.put<unknown>(`${catalogPath(accountId)}/${id}`, {
                    name,
                    transaction_type: transactionType,
                }),
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
