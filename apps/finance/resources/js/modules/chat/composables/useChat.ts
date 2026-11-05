import { ApiError } from '@/lib/api/apiClient';
import { computed, onBeforeUnmount, reactive, ref, toRef } from 'vue';
import {
    chatRepository,
    messageSchema,
    type ChatContext,
    type ChatMessage,
    type ChatRun,
    type ChatThread,
} from '../repositories/chatRepository';

type Pending = { client_request_id: string; message: string; context: ChatContext };
function conversation() {
    return reactive({
        messages: [] as ChatMessage[],
        run: null as ChatRun | null,
        busy: false,
        error: '',
        status: '',
        partial: '',
        draft: '',
        historyCursor: null as string | null,
        historyLoading: false,
        historyError: '',
        pending: null as Pending | null,
        sequence: 0,
        revision: 0,
        historyRequest: 0,
    });
}

export function useChat() {
    const threads = ref<ChatThread[]>([]);
    const thread = ref<string | null>(null);
    const threadsCursor = ref<string | null>(null);
    const threadsLoading = ref(false);
    const threadsError = ref('');
    const creating = ref(false);
    const creationError = ref('');
    const deleting = ref<string | null>(null);
    const deletionError = ref('');
    const deleted = new Set<string>();
    const states = new Map<string, ReturnType<typeof conversation>>();
    const initial = conversation();
    const selected = computed(() => (thread.value ? state(thread.value) : initial));
    const draft = computed({
        get: () => selected.value.draft,
        set: (value: string) => {
            selected.value.draft = value;
        },
    });
    let controller: AbortController | null = null;
    let generation = 0;
    let listGeneration = 0;
    let disposed = false;
    let suspended = false;
    let creationId: string | null = null;
    const failure = 'No pudimos completar la solicitud. Puedes reintentar sin duplicar el mensaje.';
    function state(id: string) {
        if (!states.has(id)) states.set(id, conversation());
        return states.get(id)!;
    }
    function append(target: ReturnType<typeof conversation>, message: ChatMessage): void {
        if (!target.messages.some((item) => item.id === message.id)) target.messages.push(message);
    }
    async function refreshThreads(more = false): Promise<void> {
        const current = ++listGeneration;
        threadsLoading.value = true;
        threadsError.value = '';
        try {
            const result = await chatRepository.threads(
                more ? (threadsCursor.value ?? undefined) : undefined,
            );
            if (disposed || current !== listGeneration) return;
            result.items = result.items.filter((item) => !deleted.has(item.id));
            threads.value = more
                ? [
                      ...threads.value,
                      ...result.items.filter(
                          (item) => !threads.value.some((existing) => existing.id === item.id),
                      ),
                  ]
                : result.items;
            threadsCursor.value = result.next_cursor;
        } catch (error) {
            if (!disposed && current === listGeneration) {
                threadsError.value = failure;
                if (error instanceof ApiError && [401, 403, 404].includes(error.status))
                    threads.value = threads.value.map((item) => ({ ...item, title: null }));
            }
        } finally {
            if (!disposed && current === listGeneration) threadsLoading.value = false;
        }
    }
    async function history(more = false): Promise<void> {
        const id = thread.value;
        if (!id || deleting.value === id || deleted.has(id)) return;
        const target = state(id);
        const current = generation;
        const request = ++target.historyRequest;
        const revision = target.revision;
        const isCurrent = (): boolean =>
            !disposed &&
            current === generation &&
            request === target.historyRequest &&
            revision === target.revision;
        target.historyLoading = true;
        target.historyError = '';
        try {
            const result = await chatRepository.messages(
                id,
                more ? (target.historyCursor ?? undefined) : undefined,
            );
            if (!isCurrent()) return;
            target.messages = more
                ? [
                      ...result.items.filter(
                          (item) => !target.messages.some((existing) => existing.id === item.id),
                      ),
                      ...target.messages,
                  ]
                : result.items;
            target.historyCursor = result.next_cursor;
            if (!more) target.run = result.active_run;
        } catch (error) {
            if (isCurrent()) {
                target.historyError = failure;
                if (error instanceof ApiError && [401, 403, 404].includes(error.status)) {
                    target.messages = [];
                    target.partial = '';
                    target.run = null;
                    threads.value = threads.value.map((item) =>
                        item.id === id ? { ...item, title: null } : item,
                    );
                }
            }
        } finally {
            if (request === target.historyRequest) target.historyLoading = false;
        }
    }
    async function follow(active: ChatRun): Promise<void> {
        if (disposed || suspended || thread.value !== active.thread_id) return;
        controller?.abort();
        const local = new AbortController();
        controller = local;
        const target = state(active.thread_id);
        target.run = active;
        try {
            await chatRepository.stream(
                active.thread_id,
                active.run_id,
                target.sequence,
                local.signal,
                (event) => {
                    if (disposed || local.signal.aborted || event.sequence <= target.sequence)
                        return;
                    target.revision++;
                    target.sequence = event.sequence;
                    if (event.type === 'message.completed') {
                        append(target, messageSchema.parse(event.payload));
                        target.partial = '';
                    }
                    if (
                        event.type === 'message.delta' &&
                        typeof event.payload === 'object' &&
                        event.payload &&
                        'text' in event.payload &&
                        typeof event.payload.text === 'string'
                    )
                        target.partial += event.payload.text;
                    if (event.type === 'status') target.status = 'Consultando tus datos…';
                    if (['run.completed', 'run.failed', 'run.cancelled'].includes(event.type)) {
                        target.run = null;
                        void refreshThreads();
                    }
                },
            );
            if (local.signal.aborted || disposed) return;
            const result = await chatRepository.run(active.thread_id, active.run_id);
            if (local.signal.aborted || disposed) return;
            target.revision++;
            if (result.message) append(target, result.message);
            if (['queued', 'running'].includes(result.status)) {
                target.run = result;
                target.error =
                    'Se interrumpió la conexión. Recupera la respuesta; el asistente sigue trabajando.';
                return;
            }
            target.run = null;
            target.partial = '';
            if (result.status === 'failed')
                target.error =
                    'El asistente no pudo responder. Puedes enviar nuevamente tu pregunta.';
            void refreshThreads();
        } catch {
            if (!disposed && !local.signal.aborted) target.error = failure;
        } finally {
            if (!local.signal.aborted) target.status = '';
        }
    }
    function suspend(): void {
        suspended = true;
        controller?.abort();
    }
    async function resume(): Promise<void> {
        suspended = false;
        const current = generation;
        await Promise.all([history(), refreshThreads()]);
        if (!disposed && current === generation && selected.value.run)
            void follow(selected.value.run);
    }
    async function select(id: string): Promise<void> {
        if (deleting.value || deleted.has(id)) return;
        generation++;
        controller?.abort();
        thread.value = id;
        suspended = false;
        await resume();
    }
    async function createThread(): Promise<ChatThread | null> {
        if (creating.value || deleting.value) return null;
        creating.value = true;
        creationError.value = '';
        creationId ??= crypto.randomUUID();
        try {
            const created = await chatRepository.create(creationId);
            if (disposed) return null;
            creationId = null;
            if (!threads.value.some((item) => item.id === created.id))
                threads.value.unshift(created);
            return created;
        } catch {
            if (!disposed) creationError.value = failure;
            return null;
        } finally {
            creating.value = false;
        }
    }
    async function newConversation(): Promise<boolean> {
        if (!thread.value && initial.pending) return send('', initial.pending.context, true);
        const current = generation;
        const created = await createThread();
        if (!created || disposed || current !== generation) return false;
        generation++;
        controller?.abort();
        thread.value = created.id;
        suspended = false;
        return true;
    }
    async function send(message: string, context: ChatContext, retry = false): Promise<boolean> {
        let target = selected.value;
        if (
            deleting.value ||
            target.busy ||
            (target.run && !retry) ||
            creating.value ||
            (!retry && (!message.trim() || message.length > 4000))
        )
            return false;
        if (target.pending && !retry) {
            target.error =
                'Primero recupera la solicitud anterior o inicia una nueva conversación.';
            return false;
        }
        const current = generation;
        target.revision++;
        target.busy = true;
        target.error = '';
        if (!retry)
            target.pending = {
                client_request_id: crypto.randomUUID(),
                message: message.trim(),
                context: { ...context },
            };
        try {
            if (!target.pending) return false;
            let id = thread.value;
            if (!id) {
                const created = await createThread();
                if (!created || disposed) return false;
                id = created.id;
                const previous = target;
                target = state(id);
                Object.assign(target, previous);
                previous.pending = null;
                previous.busy = false;
                previous.draft = '';
                if (current === generation) thread.value = id;
            }
            if (!target.pending) return false;
            const accepted = await chatRepository.send(id, target.pending);
            if (disposed) return false;
            target.revision++;
            target.pending = null;
            target.draft = '';
            target.sequence = 0;
            target.run = accepted;
            if (current === generation && thread.value === id) {
                await history();
                void follow(accepted);
            }
            return true;
        } catch {
            if (!disposed) target.error = failure;
            return false;
        } finally {
            target.busy = false;
        }
    }
    async function recover(): Promise<void> {
        if (deleting.value) return;
        const target = selected.value;
        target.error = '';
        if (target.pending) {
            await send('', target.pending.context, true);
            return;
        }
        if (target.run) {
            void follow(target.run);
            return;
        }
        if (thread.value) await resume();
        else await refreshThreads();
    }
    async function cancel(): Promise<void> {
        const target = selected.value;
        if (!target.run) return;
        try {
            await chatRepository.cancel(target.run.thread_id, target.run.run_id);
        } catch {
            if (!disposed) target.error = failure;
        }
    }
    async function remove(id: string): Promise<boolean> {
        if (deleting.value || creating.value || states.get(id)?.busy) return false;
        deleting.value = id;
        deletionError.value = '';
        try {
            try {
                await chatRepository.remove(id);
            } catch (error) {
                if (!(error instanceof ApiError) || error.status !== 404) throw error;
            }
            if (disposed) return false;
            deleted.add(id);
            listGeneration++;
            threadsLoading.value = false;
            threadsCursor.value = null;
            threads.value = threads.value.filter((item) => item.id !== id);
            const target = states.get(id);
            if (target) {
                const invalidatedRequest = target.historyRequest + 1;
                Object.assign(target, conversation());
                target.historyRequest = invalidatedRequest;
            }
            states.delete(id);
            if (thread.value === id) {
                generation++;
                controller?.abort();
                thread.value = null;
                Object.assign(initial, conversation());
            }
            await refreshThreads();
            return true;
        } catch (error) {
            if (!disposed)
                deletionError.value =
                    error instanceof ApiError && error.status === 409
                        ? 'La conversación tiene una respuesta en curso. Ábrela, detén la respuesta, espera a que termine y vuelve a intentar eliminarla.'
                        : 'No pudimos eliminar la conversación. Puedes reintentar; tu historial se conserva.';
            return false;
        } finally {
            deleting.value = null;
        }
    }
    onBeforeUnmount(() => {
        disposed = true;
        generation++;
        listGeneration++;
        controller?.abort();
        states.clear();
        thread.value = null;
        threads.value = [];
        Object.assign(initial, conversation());
    });
    return {
        threads,
        thread,
        threadsCursor,
        threadsLoading,
        threadsError,
        creating,
        creationError,
        deleting,
        deletionError,
        remove,
        draft,
        messages: toRef(() => selected.value.messages),
        run: toRef(() => selected.value.run),
        busy: toRef(() => selected.value.busy),
        error: toRef(() => selected.value.error),
        status: toRef(() => selected.value.status),
        partial: toRef(() => selected.value.partial),
        historyCursor: toRef(() => selected.value.historyCursor),
        historyLoading: toRef(() => selected.value.historyLoading),
        historyError: toRef(() => selected.value.historyError),
        refreshThreads,
        history,
        select,
        newConversation,
        send,
        recover,
        cancel,
        suspend,
        resume,
    };
}
