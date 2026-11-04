import { onBeforeUnmount, ref } from 'vue';
import {
    chatRepository,
    messageSchema,
    type ChatContext,
    type ChatMessage,
    type ChatRun,
    type ChatThread,
} from '../repositories/chatRepository';

export function useChat() {
    const threads = ref<ChatThread[]>([]);
    const thread = ref<string | null>(null);
    const messages = ref<ChatMessage[]>([]);
    const run = ref<ChatRun | null>(null);
    const busy = ref(false);
    const error = ref('');
    const status = ref('');
    const partial = ref('');
    const historyCursor = ref<string | null>(null);
    const threadsCursor = ref<string | null>(null);
    let controller: AbortController | null = null;
    let sequence = 0;
    let disposed = false;
    let generation = 0;
    let pending: { client_request_id: string; message: string; context: ChatContext } | null = null;
    let creationId = crypto.randomUUID();
    const fail = (): void => {
        error.value =
            'No pudimos completar la solicitud. Puedes reintentar sin duplicar el mensaje.';
    };
    function append(message: ChatMessage): void {
        if (!messages.value.some((item) => item.id === message.id)) messages.value.push(message);
    }
    async function refreshThreads(more = false): Promise<void> {
        try {
            const result = await chatRepository.threads(
                more ? (threadsCursor.value ?? undefined) : undefined,
            );
            if (disposed) return;
            threads.value = more ? [...threads.value, ...result.items] : result.items;
            threadsCursor.value = result.next_cursor;
        } catch {
            fail();
        }
    }
    async function history(more = false): Promise<void> {
        if (!thread.value) return;
        const id = thread.value;
        const result = await chatRepository.messages(
            id,
            more ? (historyCursor.value ?? undefined) : undefined,
        );
        if (disposed || thread.value !== id) return;
        messages.value = more
            ? [
                  ...result.items.filter(
                      (item) => !messages.value.some((current) => current.id === item.id),
                  ),
                  ...messages.value,
              ]
            : result.items;
        historyCursor.value = result.next_cursor;
        run.value = result.active_run;
    }
    async function follow(active: ChatRun): Promise<void> {
        controller?.abort();
        const local = new AbortController();
        controller = local;
        run.value = active;
        try {
            await chatRepository.stream(
                active.thread_id,
                active.run_id,
                sequence,
                local.signal,
                (event) => {
                    if (event.sequence <= sequence || disposed || local.signal.aborted) return;
                    sequence = event.sequence;
                    if (event.type === 'message.completed') {
                        append(messageSchema.parse(event.payload));
                        partial.value = '';
                    }
                    if (
                        event.type === 'message.delta' &&
                        typeof event.payload === 'object' &&
                        event.payload &&
                        'text' in event.payload &&
                        typeof event.payload.text === 'string'
                    )
                        partial.value += event.payload.text;
                    if (event.type === 'status') status.value = 'Consultando tus datos…';
                    if (
                        event.type.startsWith('run.') &&
                        ['run.completed', 'run.failed', 'run.cancelled'].includes(event.type)
                    )
                        run.value = null;
                },
            );
            if (!local.signal.aborted) {
                const result = await chatRepository.run(active.thread_id, active.run_id);
                if (disposed || local.signal.aborted) return;
                if (result.message) append(result.message);
                if (['queued', 'running'].includes(result.status)) {
                    run.value = result;
                    error.value =
                        'Se interrumpió la conexión. Recupera la respuesta; el asistente sigue trabajando.';
                } else {
                    run.value = null;
                    partial.value = '';
                    if (result.status === 'failed')
                        error.value =
                            'El asistente no pudo responder. Puedes enviar nuevamente tu pregunta.';
                }
            }
        } catch {
            if (!local.signal.aborted) fail();
        } finally {
            status.value = '';
        }
    }
    async function select(id: string): Promise<void> {
        const current = ++generation;
        controller?.abort();
        thread.value = id;
        messages.value = [];
        run.value = null;
        sequence = 0;
        partial.value = '';
        error.value = '';
        pending = null;
        try {
            await history();
            if (current === generation && run.value) void follow(run.value);
        } catch {
            fail();
        }
    }
    function newConversation(): void {
        generation++;
        controller?.abort();
        thread.value = null;
        messages.value = [];
        run.value = null;
        sequence = 0;
        partial.value = '';
        error.value = '';
        pending = null;
        creationId = crypto.randomUUID();
        historyCursor.value = null;
    }
    async function send(message: string, context: ChatContext, retry = false): Promise<boolean> {
        if (busy.value || run.value || (!retry && !message.trim())) return false;
        if (pending && !retry) {
            error.value = 'Primero recupera la solicitud anterior o inicia una nueva conversación.';
            return false;
        }
        const current = generation;
        busy.value = true;
        error.value = '';
        if (!retry)
            pending = {
                client_request_id: crypto.randomUUID(),
                message: message.trim(),
                context: { ...context },
            };
        try {
            if (!pending) return false;
            if (!thread.value) {
                const created = await chatRepository.create(creationId);
                if (disposed || current !== generation) return false;
                thread.value = created.id;
                void refreshThreads();
            }
            const accepted = await chatRepository.send(thread.value, pending);
            if (disposed || current !== generation) return false;
            pending = null;
            sequence = 0;
            run.value = accepted;
            await history();
            if (disposed || current !== generation) return false;
            void follow(accepted);
            return true;
        } catch {
            fail();
            return false;
        } finally {
            busy.value = false;
        }
    }
    async function recover(): Promise<void> {
        error.value = '';
        if (run.value) {
            void follow(run.value);
            return;
        }
        if (pending) {
            await send('', pending.context, true);
            return;
        }
        if (thread.value) await select(thread.value);
        else await refreshThreads();
    }
    async function cancel(): Promise<void> {
        if (!run.value) return;
        try {
            await chatRepository.cancel(run.value.thread_id, run.value.run_id);
        } catch {
            fail();
        }
    }
    onBeforeUnmount(() => {
        disposed = true;
        controller?.abort();
        messages.value = [];
    });
    return {
        threads,
        thread,
        messages,
        run,
        busy,
        error,
        status,
        partial,
        historyCursor,
        threadsCursor,
        refreshThreads,
        history,
        select,
        newConversation,
        send,
        recover,
        cancel,
    };
}
