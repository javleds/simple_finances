import { beforeEach, describe, expect, it, vi } from 'vitest';
const api = vi.hoisted(() => ({ get: vi.fn(), put: vi.fn() }));
vi.mock('@/lib/api/apiClient', () => ({ createApiClient: () => api }));
import { createNotificationSettingsRepository } from './notificationSettingsRepository';

beforeEach(() => vi.clearAllMocks());

describe('notification preferences API', () => {
    it('restores checked flags returned by Laravel and preserves enabled IDs when saving', async () => {
        api.get.mockResolvedValue({
            data: {
                notification_types: [
                    { id: 1, name: 'Movimientos', checked: 1 },
                    { id: 2, name: 'Resumen', checked: 0 },
                ],
                accounts: [
                    { id: 3, name: 'Casa', checked: 1 },
                    { id: 4, name: 'Ahorro', checked: 0 },
                ],
            },
        });
        const repository = createNotificationSettingsRepository();
        const settings = await repository.get();
        expect(settings.notificationTypes.map((item) => item.enabled)).toEqual([true, false]);
        expect(settings.accounts.map((item) => item.enabled)).toEqual([true, false]);
        await repository.update(settings);
        expect(api.put).toHaveBeenCalledWith('/notification-settings', {
            notification_type_ids: [1],
            account_ids: [3],
        });
    });

    it('sends explicit empty arrays when all notifications are disabled', async () => {
        await createNotificationSettingsRepository().update({
            notificationTypes: [],
            accounts: [],
        });
        expect(api.put).toHaveBeenCalledWith('/notification-settings', {
            notification_type_ids: [],
            account_ids: [],
        });
    });
});
