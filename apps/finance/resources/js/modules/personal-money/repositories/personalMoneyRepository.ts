import { personalMoneyStateSchema } from '../schemas';
import { createApiClient } from '@/lib/api/apiClient';
import type { PersonalMoneyLink, PersonalMoneySettingsPayload, PersonalMoneyState } from '../types';

const api = createApiClient();
export const personalMoneyRepository = {
    async get(page = 1, resolution: 'all' | 'pending' = 'all'): Promise<PersonalMoneyState> {
        const response = await api.get<{ data: PersonalMoneyState }>(
            `/personal-money?page=${page}&per_page=20&resolution=${resolution}`,
        );
        return personalMoneyStateSchema.parse(response.data);
    },
    async saveSettings(payload: PersonalMoneySettingsPayload): Promise<PersonalMoneyState> {
        const response = await api.put<{ data: PersonalMoneyState }>(
            '/personal-money/settings',
            payload,
        );
        return personalMoneyStateSchema.parse(response.data);
    },
    async saveLink(payload: PersonalMoneyLink): Promise<PersonalMoneyState> {
        const response = await api.put<{ data: PersonalMoneyState }>(
            '/personal-money/links',
            payload,
        );
        return personalMoneyStateSchema.parse(response.data);
    },
};
