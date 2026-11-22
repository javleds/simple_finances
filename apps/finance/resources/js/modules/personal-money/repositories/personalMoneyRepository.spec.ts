import { beforeEach, describe, expect, it, vi } from 'vitest';
import { personalMoneyRepository } from './personalMoneyRepository';

const api = vi.hoisted(() => ({ get: vi.fn(), put: vi.fn() }));
vi.mock('@/lib/api/apiClient', () => ({ createApiClient: () => api }));
beforeEach(() => vi.clearAllMocks());
const emptyState = {
    settings: null,
    accounts: [],
    available_balance: null,
    pending_count: 0,
    history: [],
    meta: { current_page: 1, last_page: 1, total: 0 },
};

describe('personal money transport', () => {
    it('rejects approximate amounts while preserving an unavailable balance', async () => {
        api.get.mockResolvedValue({ data: emptyState });
        expect((await personalMoneyRepository.get()).available_balance).toBeNull();
        api.get.mockResolvedValue({ data: { ...emptyState, available_balance: 0.1 } });
        await expect(personalMoneyRepository.get()).rejects.toThrow('expected string, received number');
    });
    it('requests a page with the pending filter and unwraps private data', async () => {
        const state = { ...emptyState, available_balance: '100.00', pending_count: 3 };
        api.get.mockResolvedValue({ data: state });
        expect(await personalMoneyRepository.get(2, 'pending')).toEqual(state);
        expect(api.get).toHaveBeenCalledWith(
            '/personal-money?page=2&per_page=20&resolution=pending',
        );
    });
    it('preserves the activation date, signed opening balance and disabled preference', async () => {
        api.put.mockResolvedValue({ data: emptyState });
        const settings = {
            account_id: 7,
            started_at: '2026-10-11',
            opening_balance: '-50.00',
            enabled: false,
        };
        await personalMoneyRepository.saveSettings(settings);
        expect(api.put).toHaveBeenCalledWith('/personal-money/settings', settings);
    });
    it('links only a source reference and personal resolution without shared private fields', async () => {
        api.put.mockResolvedValue({ data: emptyState });
        const link = {
            source_type: 'reimbursement' as const,
            source_id: 8,
            resolution: 'external' as const,
        };
        await personalMoneyRepository.saveLink(link);
        expect(api.put).toHaveBeenCalledWith('/personal-money/links', link);
    });
});
