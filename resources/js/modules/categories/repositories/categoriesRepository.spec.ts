import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createCategoriesRepository } from './categoriesRepository';

const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn() }));
vi.mock('@/lib/api/apiClient', () => ({ createApiClient: () => api }));
const category = {
    id: 1,
    name: 'Salary',
    transaction_type: 'income',
    account_id: null,
    scope: 'personal',
    transactions_count: 0,
    can_manage: true,
};

beforeEach(() => vi.clearAllMocks());
describe('category applicability transport', () => {
    it('requests a compatible catalog with the backend movement type', async () => {
        api.get.mockResolvedValue({
            data: [category],
            meta: { scope: 'personal', account_id: null, can_manage: true },
        });
        const repository = createCategoriesRepository();
        expect((await repository.list(null, 'income')).categories[0]?.transactionType).toBe(
            'income',
        );
        expect(api.get).toHaveBeenLastCalledWith('/categories?transaction_type=income');
        await repository.list('3', 'outcome');
        expect(api.get).toHaveBeenLastCalledWith('/accounts/3/categories?transaction_type=outcome');
        await repository.list('3');
        expect(api.get).toHaveBeenLastCalledWith('/accounts/3/categories');
    });

    it('sends applicability when creating and editing a category', async () => {
        api.post.mockResolvedValue({ data: category });
        api.put.mockResolvedValue({ data: { ...category, transaction_type: 'both' } });
        const repository = createCategoriesRepository();
        await repository.create(null, 'Salary', 'income');
        expect(api.post).toHaveBeenCalledWith('/categories', {
            name: 'Salary',
            transaction_type: 'income',
        });
        await repository.update(null, '1', 'Salary', 'both');
        expect(api.put).toHaveBeenCalledWith('/categories/1', {
            name: 'Salary',
            transaction_type: 'both',
        });
    });
});
