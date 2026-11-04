import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createTransactionsRepository } from './transactionsRepository';
const mocks = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('@/lib/api/apiClient', () => ({ createApiClient: () => mocks }));
beforeEach(() => mocks.get.mockReset());
describe('transaction filters transport', () => {
    it('serializes explicit false as zero in account and global lists', async () => {
        mocks.get.mockResolvedValue({
            data: [],
            meta: { summary: { income_total: 0, outcome_total: 0, balance: 0 } },
        });
        const repository = createTransactionsRepository();
        await repository.list('7', {
            filters: { categoryIds: ['2', '3'], uncategorized: true, hasAttachments: false },
        });
        await repository.listFacility({
            filters: {
                startDate: '2026-01-01',
                endDate: '2026-01-31',
                categoryIds: ['2', '3'],
                uncategorized: true,
                hasAttachments: false,
            },
        });
        for (const [path] of mocks.get.mock.calls) {
            const params = new URL(path, 'https://example.test').searchParams;
            expect(params.get('category_ids')).toBe('2,3');
            expect(params.get('uncategorized')).toBe('1');
            expect(params.get('has_attachments')).toBe('0');
        }
    });
    it('loads scoped options and preserves category origin', async () => {
        mocks.get.mockResolvedValue({
            data: {
                categories: [
                    { id: 2, name: 'Comida', account_name: null },
                    { id: 3, name: 'Comida', account_name: 'Casa' },
                ],
            },
        });
        expect(await createTransactionsRepository().filterOptions('7')).toEqual([
            { id: '2', name: 'Comida', accountName: null },
            { id: '3', name: 'Comida', accountName: 'Casa' },
        ]);
        expect(mocks.get).toHaveBeenCalledWith('/accounts/7/transactions/filter-options');
    });
});
