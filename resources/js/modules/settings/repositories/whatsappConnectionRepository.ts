import { createApiClient } from '@/lib/api/apiClient';
import { z } from 'zod';

const connectionSchema = z.object({
    status: z.enum(['unlinked', 'pending', 'linked']),
    phone_number: z.string().nullable(),
    expires_at: z.string().nullable(),
    resend_available_at: z.string().nullable(),
});

const responseSchema = z.object({ data: connectionSchema });

export type WhatsappConnection = z.infer<typeof connectionSchema>;

const apiClient = createApiClient();

export function createWhatsappConnectionRepository() {
    return {
        async get(): Promise<WhatsappConnection> {
            return responseSchema.parse(await apiClient.get('/whatsapp-connection')).data;
        },
        async requestCode(phoneNumber: string): Promise<WhatsappConnection> {
            const response = await apiClient.post('/whatsapp-verification-codes', {
                phone_number: phoneNumber,
            });
            return responseSchema.parse(response).data;
        },
        async verify(code: string): Promise<WhatsappConnection> {
            const response = await apiClient.post('/whatsapp-connection', { code });
            return responseSchema.parse(response).data;
        },
        async unlink(): Promise<WhatsappConnection> {
            return responseSchema.parse(await apiClient.delete('/whatsapp-connection')).data;
        },
    };
}
