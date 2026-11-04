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
it('does not attach a late thread creation to a newer conversation', async () => {
    const wrapper = mountChat();
    let complete: ((value: { id: string; created_at: string }) => void) | undefined;
    vi.mocked(chatRepository.create).mockImplementation(
        () =>
            new Promise((resolve) => {
                complete = resolve;
            }),
    );
    const pending = chat.send('Balance', { scope: 'global', account_id: null, route_name: null });
    chat.newConversation();
    complete?.({ id: 'obsolete', created_at: '2026-01-01' });
    expect(await pending).toBe(false);
    expect(chat.thread.value).toBeNull();
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
