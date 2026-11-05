import { defineComponent } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import { chatRepository, type ChatEvent, type ChatContext } from '../repositories/chatRepository';
import { useChat } from './useChat';
vi.mock('../repositories/chatRepository', async (original) => {
    const actual = await original<typeof import('../repositories/chatRepository')>();
    return {
        ...actual,
        chatRepository: {
            threads: vi.fn(),
            create: vi.fn(),
            messages: vi.fn(),
            send: vi.fn(),
            stream: vi.fn(),
            run: vi.fn(),
            cancel: vi.fn(),
            remove: vi.fn(),
        },
    };
});
const active = { thread_id: 'thread', run_id: 'run', status: 'queued' as const };
let chat: ReturnType<typeof useChat>;
function mountChat() {
    return mount(
        defineComponent({
            setup() {
                chat = useChat();
                return () => null;
            },
        }),
    );
}
beforeEach(() => {
    vi.resetAllMocks();
    vi.mocked(chatRepository.create).mockResolvedValue({ id: 'thread', created_at: '2026-01-01' });
    vi.mocked(chatRepository.threads).mockResolvedValue({ items: [], next_cursor: null });
    vi.mocked(chatRepository.messages).mockResolvedValue({
        items: [],
        next_cursor: null,
        active_run: active,
    });
    vi.mocked(chatRepository.send).mockResolvedValue(active);
    vi.mocked(chatRepository.stream).mockImplementation(() => new Promise(() => {}));
});
it('retries an ambiguous POST with the same identity and captured route context', async () => {
    const wrapper = mountChat();
    vi.mocked(chatRepository.send).mockRejectedValueOnce(new Error('disconnected'));
    const context: ChatContext = {
        scope: 'account',
        account_id: '42',
        route_name: 'admin.accounts.ledger',
    };
    expect(await chat.send('Balance', context)).toBe(false);
    const first = vi.mocked(chatRepository.send).mock.calls[0]?.[1];
    context.account_id = '99';
    await chat.recover();
    const retry = vi.mocked(chatRepository.send).mock.calls[1]?.[1];
    expect(retry).toEqual(first);
    expect(retry?.context.account_id).toBe('42');
    wrapper.unmount();
});
it('deduplicates replayed events and aborts the stream on logout/unmount', async () => {
    const wrapper = mountChat();
    let receive: ((event: ChatEvent) => void) | undefined;
    let signal: AbortSignal | undefined;
    vi.mocked(chatRepository.stream).mockImplementation(
        async (_thread, _run, _after, abort, callback) => {
            signal = abort;
            receive = callback;
            await new Promise(() => {});
        },
    );
    await chat.send('Balance', { scope: 'global', account_id: null, route_name: null });
    const event = {
        sequence: 1,
        type: 'message.completed',
        payload: { id: 'message', role: 'assistant', content: 'Total', created_at: '2026-01-01' },
    };
    receive?.(event);
    receive?.(event);
    expect(chat.messages.value).toHaveLength(1);
    wrapper.unmount();
    expect(signal?.aborted).toBe(true);
    expect(chat.messages.value).toEqual([]);
});
it('blocks duplicate submission while request is pending', async () => {
    const wrapper = mountChat();
    vi.mocked(chatRepository.send).mockImplementation(() => new Promise(() => {}));
    void chat.send('Balance', { scope: 'global', account_id: null, route_name: null });
    await flushPromises();
    expect(
        await chat.send('Balance', { scope: 'global', account_id: null, route_name: null }),
    ).toBe(false);
    expect(chatRepository.send).toHaveBeenCalledTimes(1);
    wrapper.unmount();
});
it('does not attach a late thread creation to a newly selected conversation', async () => {
    const wrapper = mountChat();
    let complete: ((value: { id: string; created_at: string }) => void) | undefined;
    vi.mocked(chatRepository.create).mockImplementation(
        () =>
            new Promise((resolve) => {
                complete = resolve;
            }),
    );
    const pending = chat.newConversation();
    await chat.select('other');
    complete?.({ id: 'created', created_at: '2026-01-01' });
    expect(await pending).toBe(false);
    expect(chat.thread.value).toBe('other');
    expect(chatRepository.send).not.toHaveBeenCalled();
    wrapper.unmount();
});
it('requires recovery instead of generating another key after an ambiguous send', async () => {
    const wrapper = mountChat();
    vi.mocked(chatRepository.send).mockRejectedValue(new Error('timeout'));
    const context: ChatContext = { scope: 'global', account_id: null, route_name: null };
    await chat.send('Balance', context);
    expect(await chat.send('Balance', context)).toBe(false);
    expect(chatRepository.send).toHaveBeenCalledTimes(1);
    wrapper.unmount();
});

it('creates immediately, preserves the old draft on failure and retries the same creation key', async () => {
    const wrapper = mountChat();
    await chat.select('old');
    chat.draft.value = 'Previous draft';
    vi.mocked(chatRepository.create).mockRejectedValueOnce(new Error('lost response'));
    expect(await chat.newConversation()).toBe(false);
    expect(chat.thread.value).toBe('old');
    expect(chat.draft.value).toBe('Previous draft');
    expect(chat.creationError.value).not.toBe('');
    expect(await chat.newConversation()).toBe(true);
    expect(chatRepository.create).toHaveBeenNthCalledWith(
        2,
        vi.mocked(chatRepository.create).mock.calls[0]?.[0],
    );
    expect(chat.thread.value).toBe('thread');
    expect(chat.draft.value).toBe('');
    await chat.select('old');
    expect(chat.draft.value).toBe('Previous draft');
    wrapper.unmount();
});
it('blocks duplicate creation and reuses the confirmed thread on first send', async () => {
    const wrapper = mountChat();
    const first = chat.newConversation();
    expect(await chat.newConversation()).toBe(false);
    expect(await first).toBe(true);
    await chat.send('Balance', { scope: 'global', account_id: null, route_name: null });
    expect(chatRepository.create).toHaveBeenCalledTimes(1);
    expect(chatRepository.send).toHaveBeenCalledWith('thread', expect.anything());
    wrapper.unmount();
});
it('preserves ambiguous requests across conversation switches', async () => {
    const wrapper = mountChat();
    vi.mocked(chatRepository.messages).mockResolvedValue({
        items: [],
        next_cursor: null,
        active_run: null,
    });
    vi.mocked(chatRepository.send).mockRejectedValueOnce(new Error('lost response'));
    await chat.select('thread');
    await chat.send('Balance', { scope: 'global', account_id: null, route_name: null });
    const original = vi.mocked(chatRepository.send).mock.calls[0];
    await chat.select('other');
    chat.draft.value = 'Other draft';
    await chat.select('thread');
    await chat.recover();
    expect(vi.mocked(chatRepository.send).mock.calls[1]).toEqual(original);
    await chat.select('other');
    expect(chat.draft.value).toBe('Other draft');
    wrapper.unmount();
});
it('ignores late stream events after switching without cancelling the server run', async () => {
    const wrapper = mountChat();
    let receive: ((event: ChatEvent) => void) | undefined;
    vi.mocked(chatRepository.stream).mockImplementation(
        async (_id, _run, _after, _signal, callback) => {
            receive = callback;
            await new Promise(() => {});
        },
    );
    await chat.send('Balance', { scope: 'global', account_id: null, route_name: null });
    const previous = receive;
    vi.mocked(chatRepository.messages).mockResolvedValue({
        items: [],
        next_cursor: null,
        active_run: null,
    });
    await chat.select('other');
    previous?.({
        sequence: 1,
        type: 'message.completed',
        payload: { id: 'old', role: 'assistant', content: 'Old answer', created_at: '2026-01-01' },
    });
    expect(chat.messages.value).toEqual([]);
    expect(chatRepository.cancel).not.toHaveBeenCalled();
    wrapper.unmount();
});
it('refreshes titles after run completion and separates list failures', async () => {
    const wrapper = mountChat();
    let receive: ((event: ChatEvent) => void) | undefined;
    vi.mocked(chatRepository.stream).mockImplementation(
        async (_id, _run, _after, _signal, callback) => {
            receive = callback;
            await new Promise(() => {});
        },
    );
    await chat.send('Balance', { scope: 'global', account_id: null, route_name: null });
    vi.mocked(chatRepository.threads).mockResolvedValueOnce({
        items: [{ id: 'thread', title: 'Balance de mis cuentas', created_at: '2026-01-01' }],
        next_cursor: null,
    });
    receive?.({ sequence: 1, type: 'run.completed', payload: {} });
    await flushPromises();
    expect(chat.threads.value[0]?.title).toBe('Balance de mis cuentas');
    vi.mocked(chatRepository.threads).mockRejectedValueOnce(new Error('offline'));
    await chat.refreshThreads();
    expect(chat.threadsError.value).not.toBe('');
    expect(chat.error.value).toBe('');
    expect(chat.threads.value).toHaveLength(1);
    wrapper.unmount();
});
it('keeps late send completion in its original conversation and preserves another draft', async () => {
    const wrapper = mountChat();
    vi.mocked(chatRepository.messages).mockResolvedValue({
        items: [],
        next_cursor: null,
        active_run: null,
    });
    await chat.select('thread');
    chat.draft.value = 'Original';
    let accept: ((value: typeof active) => void) | undefined;
    vi.mocked(chatRepository.send).mockImplementation(
        () =>
            new Promise((resolve) => {
                accept = resolve;
            }),
    );
    const sending = chat.send('Original', { scope: 'global', account_id: null, route_name: null });
    await chat.select('other');
    chat.draft.value = 'Other draft';
    accept?.(active);
    expect(await sending).toBe(true);
    expect(chat.thread.value).toBe('other');
    expect(chat.draft.value).toBe('Other draft');
    expect(chat.run.value).toBeNull();
    wrapper.unmount();
});
it('does not restore late history after logout', async () => {
    const wrapper = mountChat();
    let resolve: ((value: { items: []; next_cursor: null; active_run: null }) => void) | undefined;
    vi.mocked(chatRepository.messages).mockImplementation(
        () =>
            new Promise((done) => {
                resolve = done;
            }),
    );
    const selection = chat.select('thread');
    wrapper.unmount();
    resolve?.({ items: [], next_cursor: null, active_run: null });
    await selection;
    expect(chat.thread.value).toBeNull();
    expect(chat.messages.value).toEqual([]);
    expect(chatRepository.stream).not.toHaveBeenCalled();
});
it('does not replace a completed response with an older same-thread history snapshot', async () => {
    const wrapper = mountChat();
    vi.mocked(chatRepository.messages).mockResolvedValue({
        items: [],
        next_cursor: null,
        active_run: null,
    });
    await chat.select('thread');
    let completeHistory:
        | ((value: { items: []; next_cursor: null; active_run: typeof active }) => void)
        | undefined;
    vi.mocked(chatRepository.messages).mockImplementationOnce(
        () =>
            new Promise((resolve) => {
                completeHistory = resolve;
            }),
    );
    const older = chat.resume();
    let receive: ((event: ChatEvent) => void) | undefined;
    vi.mocked(chatRepository.stream).mockImplementation(
        async (_thread, _run, _after, _abort, callback) => {
            receive = callback;
            await new Promise(() => {});
        },
    );
    await chat.send('Balance', { scope: 'global', account_id: null, route_name: null });
    receive?.({
        sequence: 1,
        type: 'message.completed',
        payload: {
            id: 'answer',
            role: 'assistant',
            content: 'Latest answer',
            created_at: '2026-01-01',
        },
    });
    receive?.({ sequence: 2, type: 'run.completed', payload: {} });
    completeHistory?.({ items: [], next_cursor: null, active_run: active });
    await older;
    expect(chat.messages.value[0]?.content).toBe('Latest answer');
    expect(chat.run.value).toBeNull();
    wrapper.unmount();
});
it('invalidates pending pagination when live events mutate the conversation', async () => {
    const wrapper = mountChat();
    let receive: ((event: ChatEvent) => void) | undefined;
    vi.mocked(chatRepository.stream).mockImplementation(
        async (_thread, _run, _after, _abort, callback) => {
            receive = callback;
            await new Promise(() => {});
        },
    );
    await chat.send('Balance', { scope: 'global', account_id: null, route_name: null });
    let completeHistory:
        | ((value: { items: []; next_cursor: null; active_run: typeof active }) => void)
        | undefined;
    vi.mocked(chatRepository.messages).mockImplementationOnce(
        () =>
            new Promise((resolve) => {
                completeHistory = resolve;
            }),
    );
    const older = chat.history(true);
    receive?.({ sequence: 1, type: 'run.completed', payload: {} });
    completeHistory?.({ items: [], next_cursor: null, active_run: active });
    await older;
    expect(chat.run.value).toBeNull();
    expect(chat.historyLoading.value).toBe(false);
    wrapper.unmount();
});

it('recovers the first question when retrying failed thread creation', async () => {
    const wrapper = mountChat();
    const context: ChatContext = {
        scope: 'account',
        account_id: '42',
        route_name: 'admin.accounts.transactions',
    };
    chat.draft.value = 'Balance de esta cuenta';
    vi.mocked(chatRepository.create).mockRejectedValueOnce(new Error('unavailable'));
    expect(await chat.send(chat.draft.value, context)).toBe(false);
    expect(chat.draft.value).toBe('Balance de esta cuenta');
    expect(chatRepository.send).not.toHaveBeenCalled();
    context.account_id = '99';
    expect(await chat.newConversation()).toBe(true);
    expect(chatRepository.create).toHaveBeenNthCalledWith(
        2,
        vi.mocked(chatRepository.create).mock.calls[0]?.[0],
    );
    expect(chatRepository.send).toHaveBeenCalledExactlyOnceWith(
        'thread',
        expect.objectContaining({
            message: 'Balance de esta cuenta',
            context: {
                scope: 'account',
                account_id: '42',
                route_name: 'admin.accounts.transactions',
            },
        }),
    );
    expect(chat.draft.value).toBe('');
    wrapper.unmount();
});

it('clears selected conversation and ignores a stale list after deletion', async () => {
    const wrapper = mountChat();
    await chat.select('thread');
    chat.draft.value = 'Private draft';
    let resolveList!: (value: {
        items: { id: string; created_at: string }[];
        next_cursor: null;
    }) => void;
    vi.mocked(chatRepository.threads).mockImplementationOnce(
        () =>
            new Promise((resolve) => {
                resolveList = resolve;
            }),
    );
    const listing = chat.refreshThreads();
    expect(await chat.remove('thread')).toBe(true);
    resolveList({ items: [{ id: 'thread', created_at: '2026-01-01' }], next_cursor: null });
    await listing;
    await chat.select('thread');
    expect(chat.thread.value).toBeNull();
    expect(chat.messages.value).toEqual([]);
    expect(chat.draft.value).toBe('');
    expect(chat.run.value).toBeNull();
    expect(chat.threads.value).toEqual([]);
    wrapper.unmount();
});
it('preserves another draft and blocks duplicate deletion, sends and selection', async () => {
    const wrapper = mountChat();
    await chat.select('other');
    chat.draft.value = 'Keep me';
    let complete!: () => void;
    vi.mocked(chatRepository.remove).mockImplementationOnce(
        () =>
            new Promise((resolve) => {
                complete = resolve;
            }),
    );
    const deleting = chat.remove('thread');
    expect(await chat.remove('thread')).toBe(false);
    await chat.select('thread');
    expect(
        await chat.send('Balance', { scope: 'global', account_id: null, route_name: null }),
    ).toBe(false);
    complete();
    expect(await deleting).toBe(true);
    expect(chat.thread.value).toBe('other');
    expect(chat.draft.value).toBe('Keep me');
    expect(chatRepository.remove).toHaveBeenCalledTimes(1);
    wrapper.unmount();
});
it('keeps a conversation on conflict and accepts 404 as already deleted', async () => {
    const { ApiError } = await import('@/lib/api/apiClient');
    const wrapper = mountChat();
    await chat.select('thread');
    chat.draft.value = 'Keep until success';
    vi.mocked(chatRepository.remove).mockRejectedValueOnce(new ApiError('Busy', 409));
    expect(await chat.remove('thread')).toBe(false);
    expect(chat.deletionError.value).toContain('detén');
    expect(chat.draft.value).toBe('Keep until success');
    vi.mocked(chatRepository.remove).mockRejectedValueOnce(new ApiError('Missing', 404));
    expect(await chat.remove('thread')).toBe(true);
    expect(chat.thread.value).toBeNull();
    wrapper.unmount();
});
it('invalidates late message history and streaming callbacks when the selected thread is deleted', async () => {
    const wrapper = mountChat();
    let receive: ((event: ChatEvent) => void) | undefined;
    let signal: AbortSignal | undefined;
    vi.mocked(chatRepository.stream).mockImplementation(
        async (_id, _run, _after, abort, callback) => {
            signal = abort;
            receive = callback;
            await new Promise(() => {});
        },
    );
    await chat.select('thread');
    let complete!: (value: Awaited<ReturnType<typeof chatRepository.messages>>) => void;
    vi.mocked(chatRepository.messages).mockImplementationOnce(
        () =>
            new Promise((resolve) => {
                complete = resolve;
            }),
    );
    const history = chat.history();
    await chat.remove('thread');
    complete({
        items: [
            {
                id: 'old',
                role: 'user',
                content: 'Private',
                created_at: '',
                references: [],
                warnings: [],
            },
        ],
        next_cursor: null,
        active_run: active,
    });
    await history;
    receive?.({
        sequence: 2,
        type: 'message.completed',
        payload: { id: 'late', role: 'assistant', content: 'Private', created_at: '' },
    });
    expect(signal?.aborted).toBe(true);
    expect(chat.messages.value).toEqual([]);
    expect(chat.run.value).toBeNull();
    wrapper.unmount();
});

it('restarts pagination after deleting its boundary without discarding another draft', async () => {
    const wrapper = mountChat();
    await chat.select('other');
    chat.draft.value = 'Keep this draft';
    vi.mocked(chatRepository.threads).mockResolvedValueOnce({
        items: [{ id: 'boundary', created_at: '2026-01-01' }],
        next_cursor: 'boundary',
    });
    await chat.refreshThreads();
    vi.mocked(chatRepository.threads).mockResolvedValueOnce({
        items: [{ id: 'replacement', created_at: '2025-12-31' }],
        next_cursor: 'replacement',
    });
    expect(await chat.remove('boundary')).toBe(true);
    expect(chatRepository.threads).toHaveBeenLastCalledWith(undefined);
    expect(chat.threadsCursor.value).toBe('replacement');
    await chat.refreshThreads(true);
    expect(chatRepository.threads).toHaveBeenLastCalledWith('replacement');
    expect(chat.thread.value).toBe('other');
    expect(chat.draft.value).toBe('Keep this draft');
    wrapper.unmount();
});

it('restores conversation context from history without sharing it with other threads', async () => {
    const wrapper = mountChat();
    const remembered = {
        scope: 'account' as const,
        active_account: { account_id: '42', name: 'Retiro' },
        candidates: [],
        awaiting_account: false,
    };
    vi.mocked(chatRepository.messages).mockResolvedValueOnce({
        items: [],
        next_cursor: null,
        active_run: null,
        conversation_context: remembered,
    });
    await chat.select('old');
    expect(chat.conversationContext.value).toEqual(remembered);
    await chat.select('other');
    expect(chat.conversationContext.value).toBeNull();
    vi.mocked(chatRepository.messages).mockResolvedValueOnce({
        items: [],
        next_cursor: null,
        active_run: null,
        conversation_context: remembered,
    });
    await chat.select('old');
    await chat.newConversation();
    expect(chat.conversationContext.value).toBeNull();
    wrapper.unmount();
});

it('updates account context from completed events and clears it on denied history', async () => {
    const wrapper = mountChat();
    let receive: ((event: ChatEvent) => void) | undefined;
    vi.mocked(chatRepository.stream).mockImplementation(
        async (_thread, _run, _after, _signal, callback) => {
            receive = callback;
            await new Promise(() => {});
        },
    );
    await chat.send('Balance', { scope: 'global', account_id: null, route_name: null });
    receive?.({
        sequence: 1,
        type: 'message.completed',
        payload: {
            id: 'answer',
            role: 'assistant',
            content: 'Elige una cuenta',
            created_at: '2026-01-01',
            conversation_context: {
                scope: 'multiple',
                active_account: null,
                candidates: [{ account_id: '42', name: 'Retiro' }],
                awaiting_account: true,
            },
        },
    });
    expect(chat.conversationContext.value?.awaiting_account).toBe(true);
    const { ApiError } = await import('@/lib/api/apiClient');
    vi.mocked(chatRepository.messages).mockRejectedValueOnce(new ApiError('Denied', 403));
    await chat.history();
    expect(chat.conversationContext.value).toBeNull();
    wrapper.unmount();
});
