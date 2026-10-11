import { z } from 'zod';
import { createApiClient } from '@/lib/api/apiClient';
import type { AttachmentConfig, TransactionAttachment } from '../types';

const client = createApiClient();
const attachmentSchema = z.object({
    id: z.union([z.string(), z.number()]).transform(String),
    original_name: z.string(),
    mime_type: z
        .string()
        .nullable()
        .transform((value) => value ?? ''),
    size_bytes: z.number(),
    status: z.enum(['pending', 'processing', 'ready', 'failed']),
    error_message: z.string().nullable(),
    can_manage: z.boolean(),
    can_retry: z.boolean(),
    created_at: z.string(),
});
const configSchema = z.object({
    max_files: z.number().int().positive(),
    max_file_bytes: z.object({
        image: z.number(),
        pdf: z.number(),
        xml: z.number(),
        zip: z.number(),
    }),
    max_transaction_bytes: z.number(),
    accepted_extensions: z.array(z.string()),
    poll_interval_ms: z.number().positive(),
});
function path(transactionId: string, attachmentId?: string): string {
    return `/transactions/${encodeURIComponent(transactionId)}/attachments${attachmentId ? `/${encodeURIComponent(attachmentId)}` : ''}`;
}
export const transactionAttachmentsRepository = {
    async config(): Promise<AttachmentConfig> {
        const response = await client.get<{ data: unknown }>('/transaction-attachments/config');
        return configSchema.parse(response.data);
    },
    async list(transactionId: string): Promise<{
        data: TransactionAttachment[];
        meta: { can_manage: boolean; count: number; total_bytes: number };
    }> {
        return z
            .object({
                data: z.array(attachmentSchema),
                meta: z.object({
                    can_manage: z.boolean(),
                    count: z.number(),
                    total_bytes: z.number(),
                }),
            })
            .parse(await client.get(path(transactionId)));
    },
    async upload(
        transactionId: string,
        file: File,
        uploadId: string,
    ): Promise<TransactionAttachment> {
        const form = new FormData();
        form.append('file', file);
        form.append('upload_id', uploadId);
        const response = await client.post<{ data: unknown }>(path(transactionId), form);
        return attachmentSchema.parse(response.data);
    },
    async retry(transactionId: string, attachmentId: string): Promise<void> {
        await client.post(`${path(transactionId, attachmentId)}/retry`);
    },
    async remove(transactionId: string, attachmentId: string): Promise<void> {
        await client.delete(path(transactionId, attachmentId));
    },
    download(transactionId: string, attachmentId: string): Promise<Blob> {
        return client.download(`${path(transactionId, attachmentId)}/download`);
    },
};
