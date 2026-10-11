import { describe, expect, it } from 'vitest';
import { categoryCatalogSchema, categoryNameSchema } from './categorySchemas';

describe('category catalog contracts', () => {
    it('preserves shared permissions and usage from the API', () => {
        const catalog = categoryCatalogSchema.parse({
            data: [
                {
                    id: 4,
                    name: 'Comida',
                    account_id: 9,
                    scope: 'shared',
                    transactions_count: 7,
                    can_manage: false,
                },
            ],
            meta: { scope: 'shared', account_id: 9, account_name: 'Casa', can_manage: false },
        });
        expect(catalog).toEqual({
            categories: [
                {
                    id: '4',
                    name: 'Comida',
                    accountId: '9',
                    scope: 'shared',
                    transactionsCount: 7,
                    canManage: false,
                },
            ],
            scope: 'shared',
            accountId: '9',
            accountName: 'Casa',
            canManage: false,
        });
    });

    it('supports an empty personal catalog without a default category', () => {
        expect(
            categoryCatalogSchema.parse({
                data: [],
                meta: { scope: 'personal', account_id: null, can_manage: true },
            }),
        ).toMatchObject({ categories: [], accountId: null, scope: 'personal' });
    });

    it('rejects empty names and names longer than 100 characters', () => {
        expect(categoryNameSchema.safeParse({ name: '  ' }).success).toBe(false);
        expect(categoryNameSchema.safeParse({ name: 'a'.repeat(101) }).success).toBe(false);
        expect(categoryNameSchema.parse({ name: ' Comida ' })).toEqual({ name: 'Comida' });
    });
});
