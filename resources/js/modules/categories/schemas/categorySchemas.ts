import { z } from 'zod';
import type { Category, CategoryCatalog } from '../types';

const entityId = z.union([z.string(), z.number()]).transform(String);

export const categorySchema = z
    .object({
        id: entityId,
        name: z.string(),
        account_id: entityId.nullable(),
        scope: z.enum(['personal', 'shared']),
        transactions_count: z.coerce.number(),
        can_manage: z.boolean(),
    })
    .transform<Category>((value) => ({
        id: value.id,
        name: value.name,
        accountId: value.account_id,
        scope: value.scope,
        transactionsCount: value.transactions_count,
        canManage: value.can_manage,
    }));

export const categoryResponseSchema = z
    .object({ data: categorySchema })
    .transform((value) => value.data);
export const categoryCatalogSchema = z
    .object({
        data: z.array(categorySchema),
        meta: z.object({
            scope: z.enum(['personal', 'shared']),
            account_id: entityId.nullable(),
            account_name: z.string().nullable().optional(),
            can_manage: z.boolean(),
        }),
    })
    .transform<CategoryCatalog>((value) => ({
        categories: value.data,
        scope: value.meta.scope,
        accountId: value.meta.account_id,
        accountName: value.meta.account_name ?? null,
        canManage: value.meta.can_manage,
    }));

export const categoryNameSchema = z.object({
    name: z.string().trim().min(1, 'Escribe un nombre.').max(100, 'Usa hasta 100 caracteres.'),
});
