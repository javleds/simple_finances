import { z } from 'zod';
import { createApiClient, getStoredAuthToken, ApiError } from '@/lib/api/apiClient';

export type ChatContext = {
    scope: 'global' | 'account';
    account_id: string | null;
    route_name: string | null;
};
export const messageSchema = z.object({
    id: z.string(),
    role: z.enum(['user', 'assistant']),
    content: z.string(),
    created_at: z.string(),
    references: z
        .array(
            z.object({
                account_id: z.union([z.string(), z.number()]),
                transaction_id: z.union([z.string(), z.number()]).optional(),
            }),
        )
        .default([]),
    warnings: z.array(z.string()).default([]),
});
const runSchema = z.object({
    thread_id: z.string(),
    run_id: z.string(),
    status: z.enum(['queued', 'running', 'completed', 'failed', 'cancelled']),
    error: z.string().nullable().optional(),
    message: messageSchema.nullable().optional(),
});
export type ChatMessage = z.infer<typeof messageSchema>;
export type ChatRun = z.infer<typeof runSchema>;
export const threadSchema = z.object({
    id: z.string(),
    title: z.string().nullable().optional(),
    created_at: z.string(),
    updated_at: z.string().optional(),
});
export type ChatThread = z.infer<typeof threadSchema>;
export type ChatEvent = { sequence: number; type: string; payload: unknown };
const eventSchema = z.object({
    sequence: z.number().int(),
    type: z.string(),
    payload: z.unknown(),
});
const api = createApiClient();
async function data<T>(request: Promise<{ data: unknown }>, schema: z.ZodType<T>): Promise<T> {
    return schema.parse((await request).data);
}
export const chatRepository = {
    threads: (cursor?: string) =>
        data(
            api.get(`/chat/threads${cursor ? `?cursor=${encodeURIComponent(cursor)}` : ''}`),
            z.object({ items: z.array(threadSchema), next_cursor: z.string().nullable() }),
        ),
    create: (id: string) =>
        data(api.post('/chat/threads', { client_request_id: id }), threadSchema),
    async remove(id: string): Promise<void> {
        await api.delete(`/chat/threads/${id}`);
    },
    messages: (id: string, cursor?: string) =>
        data(
            api.get(
                `/chat/threads/${id}/messages${cursor ? `?cursor=${encodeURIComponent(cursor)}` : ''}`,
            ),
            z.object({
                items: z.array(messageSchema),
                next_cursor: z.string().nullable(),
                active_run: runSchema.nullable(),
            }),
        ),
    send: (
        id: string,
        payload: { client_request_id: string; message: string; context: ChatContext },
    ) => data(api.post(`/chat/threads/${id}/runs`, payload), runSchema),
    run: (thread: string, run: string) =>
        data(api.get(`/chat/threads/${thread}/runs/${run}`), runSchema),
    cancel: (thread: string, run: string) =>
        data(api.post(`/chat/threads/${thread}/runs/${run}/cancel`), runSchema),
    async stream(
        thread: string,
        run: string,
        after: number,
        signal: AbortSignal,
        receive: (event: ChatEvent) => void,
    ): Promise<void> {
        const base = (import.meta.env.VITE_API_BASE_URL?.trim() || '/api').replace(/\/$/, '');
        const response = await fetch(
            `${base}/chat/threads/${thread}/runs/${run}/events?after=${after}`,
            {
                headers: {
                    Accept: 'text/event-stream',
                    Authorization: `Bearer ${getStoredAuthToken() ?? ''}`,
                },
                signal,
            },
        );
        if (!response.ok)
            throw new ApiError('No se pudo recuperar la conversación.', response.status);
        if (!response.body) throw new Error('Stream no disponible.');
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        const parser = createEventParser(receive);
        try {
            for (;;) {
                const { done, value } = await reader.read();
                if (done) {
                    parser(decoder.decode());
                    break;
                }
                parser(decoder.decode(value, { stream: true }));
            }
        } finally {
            await reader.cancel();
            reader.releaseLock();
        }
    },
};

export function createEventParser(receive: (event: ChatEvent) => void): (chunk: string) => void {
    let buffer = '';
    return (chunk: string): void => {
        buffer += chunk;
        for (;;) {
            const boundary = /\r?\n\r?\n/.exec(buffer);
            if (!boundary || boundary.index === undefined) return;
            const frame = buffer.slice(0, boundary.index);
            buffer = buffer.slice(boundary.index + boundary[0].length);
            const value = frame
                .split(/\r?\n/)
                .filter((line) => line.startsWith('data:'))
                .map((line) => line.slice(5).trimStart())
                .join('\n');
            if (value) receive(eventSchema.parse(JSON.parse(value)));
        }
    };
}
