import { defineComponent, nextTick, reactive, ref } from 'vue';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import PrimeVue from 'primevue/config';
import AppModal from '@/modules/shared/components/AppModal.vue';
import ChatPanel from './ChatPanel.vue';
import ChatLauncher from './ChatLauncher.vue';
import ChatConversationList from './ChatConversationList.vue';
import { chatRepository } from '../repositories/chatRepository';

const route = reactive({ name: 'admin.dashboard', params: {} as { accountId?: string } });
vi.mock('vue-router', () => ({ useRoute: () => route }));
vi.mock('@/modules/accounts/composables/useAccountRelationshipAccount', () => ({
    useAccountRelationshipAccount: () => ({ account: ref(null) }),
}));
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
const DialogStub = defineComponent({
    name: 'TestDialog',
    props: ['visible', 'modal', 'blockScroll', 'position', 'dismissableMask', 'closeOnEscape'],
    template:
        '<section v-if="visible" role="dialog"><header><slot name="header" /></header><slot /><footer><slot name="footer" /></footer></section>',
});
const global = { plugins: [PrimeVue], stubs: { Dialog: DialogStub } };
const wrappers: VueWrapper[] = [];
const existing = {
    id: '11111111-1111-1111-1111-111111111111',
    title: 'Gastos por categoría',
    created_at: '2026-11-04T06:00:00Z',
};
function keep(wrapper: VueWrapper): VueWrapper {
    wrappers.push(wrapper);
    return wrapper;
}
beforeEach(() => {
    vi.resetAllMocks();
    route.name = 'admin.dashboard';
    route.params = {};
    vi.stubGlobal(
        'matchMedia',
        vi.fn(() => ({ matches: true, addEventListener: vi.fn(), removeEventListener: vi.fn() })),
    );
    HTMLElement.prototype.scrollTo = vi.fn();
    vi.mocked(chatRepository.threads).mockResolvedValue({ items: [existing], next_cursor: null });
    vi.mocked(chatRepository.messages).mockResolvedValue({
        items: [],
        next_cursor: null,
        active_run: null,
    });
    vi.mocked(chatRepository.create).mockResolvedValue({
        id: 'new-thread',
        created_at: '2026-11-04T07:00:00Z',
    });
});
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    document.head.querySelector('meta[name="chat-enabled"]')?.remove();
    vi.unstubAllGlobals();
});
it('keeps the navbar trigger visible when disabled and opens without API calls', async () => {
    const wrapper = keep(mount(ChatLauncher, { global }));
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    const trigger = wrapper.get('#chat-trigger');
    expect(trigger.attributes('aria-expanded')).toBe('false');
    await trigger.trigger('click');
    await flushPromises();
    expect(trigger.attributes('aria-expanded')).toBe('true');
    expect(wrapper.text()).toContain('El asistente aún no está habilitado');
    expect(chatRepository.threads).not.toHaveBeenCalled();
    expect(chatRepository.create).not.toHaveBeenCalled();
    await trigger.trigger('click');
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
});
it('opens without creating, replaces the transcript with history and continues a selected ID', async () => {
    const wrapper = keep(mount(ChatPanel, { props: { open: true, enabled: true }, global }));
    await flushPromises();
    expect(chatRepository.create).not.toHaveBeenCalled();
    expect(wrapper.find('#assistant-message').exists()).toBe(true);
    await wrapper.get('[aria-label="Abrir historial"]').trigger('click');
    await flushPromises();
    expect(wrapper.find('[role="log"]').exists()).toBe(false);
    expect(wrapper.find('#assistant-message').exists()).toBe(false);
    expect(wrapper.text()).toContain(existing.title);
    expect(wrapper.text()).toContain(existing.id);
    const row = wrapper.findAll('button').find((button) => button.text().includes(existing.title));
    await row!.trigger('click');
    await flushPromises();
    expect(chatRepository.messages).toHaveBeenCalledWith(existing.id, undefined);
    expect(wrapper.find('#assistant-message').exists()).toBe(true);
    expect(wrapper.text()).toContain(`ID: ${existing.id}`);
});
it('creates a thread from history and displays its server ID', async () => {
    const wrapper = keep(mount(ChatPanel, { props: { open: true, enabled: true }, global }));
    await flushPromises();
    await wrapper.get('[aria-label="Abrir historial"]').trigger('click');
    await flushPromises();
    await wrapper.get('[aria-label="Nuevo chat"]').trigger('click');
    await flushPromises();
    expect(chatRepository.create).toHaveBeenCalledTimes(1);
    expect(wrapper.find('[role="log"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('ID: new-thread');
});
it('renders fallback titles, selected state and pagination without interpreting title markup', async () => {
    const wrapper = keep(
        mount(ChatConversationList, {
            global,
            props: {
                threads: [
                    { ...existing, title: '<img src=x onerror=alert(1)>' },
                    { id: 'legacy', created_at: existing.created_at },
                ],
                selected: existing.id,
                loading: false,
                error: '',
                hasMore: true,
            },
        }),
    );
    expect(wrapper.find('img').exists()).toBe(false);
    expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
    expect(wrapper.text()).toContain('Conversación del');
    await wrapper.get('[aria-current="true"]').trigger('click');
    expect(wrapper.emitted('select')).toEqual([[existing.id]]);
    await wrapper
        .findAll('button')
        .find((button) => button.text() === 'Más conversaciones')!
        .trigger('click');
    expect(wrapper.emitted('more')).toHaveLength(1);
});
it('configures desktop chat as nonmodal and mobile chat as modal without changing standard defaults', async () => {
    const desktop = keep(
        mount(AppModal, { global, props: { open: true, title: 'Chat', presentation: 'chat' } }),
    );
    await nextTick();
    expect(desktop.getComponent(DialogStub).props()).toMatchObject({
        modal: false,
        blockScroll: false,
        position: 'bottomright',
        dismissableMask: false,
        closeOnEscape: false,
    });
    vi.stubGlobal(
        'matchMedia',
        vi.fn(() => ({ matches: false, addEventListener: vi.fn(), removeEventListener: vi.fn() })),
    );
    const mobile = keep(
        mount(AppModal, { global, props: { open: true, title: 'Chat', presentation: 'chat' } }),
    );
    await nextTick();
    expect(mobile.getComponent(DialogStub).props()).toMatchObject({
        modal: true,
        blockScroll: true,
        dismissableMask: false,
    });
    const standard = keep(
        mount(AppModal, { global, props: { open: true, title: 'Crear cuenta' } }),
    );
    expect(standard.getComponent(DialogStub).props()).toMatchObject({
        modal: true,
        blockScroll: true,
        position: 'center',
        dismissableMask: true,
        closeOnEscape: true,
    });
    expect(standard.text()).toContain('Cerrar');
});

it('handles Escape locally for chat and preserves bubbling for standard dialogs', async () => {
    const listener = vi.fn();
    document.body.addEventListener('keydown', listener);
    try {
        const standard = keep(
            mount(AppModal, {
                attachTo: document.body,
                global,
                props: { open: true, title: 'Crear' },
            }),
        );
        await standard.get('[role="dialog"]').trigger('keydown', { key: 'Escape', code: 'Escape' });
        expect(listener).toHaveBeenCalledTimes(1);
        const chat = keep(
            mount(AppModal, {
                attachTo: document.body,
                global,
                props: { open: true, title: 'Chat', presentation: 'chat' },
            }),
        );
        await chat.get('[role="dialog"]').trigger('keydown', { key: 'Escape', code: 'Escape' });
        expect(chat.emitted('close')).toHaveLength(1);
        expect(listener).toHaveBeenCalledTimes(1);
        await chat
            .get('[role="dialog"]')
            .trigger('keydown', { key: 'Escape', code: 'Escape', isComposing: true });
        expect(chat.emitted('close')).toHaveLength(1);
    } finally {
        document.body.removeEventListener('keydown', listener);
    }
});

it('confirms deletion, supports cancellation and preserves failed requests for retry', async () => {
    const wrapper = keep(mount(ChatPanel, { props: { open: true, enabled: true }, global }));
    await flushPromises();
    await wrapper.get('[aria-label="Abrir historial"]').trigger('click');
    await flushPromises();
    expect(wrapper.find('button button').exists()).toBe(false);
    await wrapper.get('[aria-label="Eliminar conversación Gastos por categoría"]').trigger('click');
    expect(wrapper.text()).toContain('no se puede deshacer');
    expect(chatRepository.remove).not.toHaveBeenCalled();
    await wrapper
        .findAll('button')
        .find((button) => button.text() === 'Cancelar')!
        .trigger('click');
    expect(wrapper.text()).not.toContain('no se puede deshacer');
    await wrapper.get('[aria-label="Eliminar conversación Gastos por categoría"]').trigger('click');
    vi.mocked(chatRepository.remove).mockRejectedValueOnce(new Error('offline'));
    await wrapper
        .findAll('button')
        .find((button) => button.text() === 'Eliminar permanentemente')!
        .trigger('click');
    await flushPromises();
    expect(wrapper.get('[role="alert"]').text()).toContain('No pudimos eliminar');
    expect(wrapper.find('[aria-label="Eliminar conversación Gastos por categoría"]').exists()).toBe(
        true,
    );
    await wrapper
        .findAll('button')
        .find((button) => button.text() === 'Eliminar permanentemente')!
        .trigger('click');
    await flushPromises();
    expect(wrapper.find('[aria-label="Eliminar conversación Gastos por categoría"]').exists()).toBe(
        false,
    );
    expect(wrapper.text()).not.toContain('no se puede deshacer');
});

it('displays the remembered account after reopening history despite navigation to another account', async () => {
    vi.mocked(chatRepository.messages).mockResolvedValue({
        items: [],
        next_cursor: null,
        active_run: null,
        conversation_context: {
            scope: 'account',
            active_account: { account_id: '42', name: 'Retiro de Javi' },
            candidates: [],
            awaiting_account: false,
        },
    });
    const wrapper = keep(mount(ChatPanel, { props: { open: true, enabled: true }, global }));
    await flushPromises();
    await wrapper.get('[aria-label="Abrir historial"]').trigger('click');
    await flushPromises();
    await wrapper
        .findAll('button')
        .find((button) => button.text().includes(existing.title))!
        .trigger('click');
    await flushPromises();
    expect(wrapper.text()).toContain('Cuenta: Retiro de Javi');
    route.name = 'admin.accounts.transactions';
    route.params = { accountId: '99' };
    await nextTick();
    expect(wrapper.text()).toContain('Cuenta: Retiro de Javi');
    expect(wrapper.text()).not.toContain('cuenta #99');
    await wrapper.setProps({ open: false });
    await wrapper.setProps({ open: true });
    await flushPromises();
    expect(wrapper.text()).toContain('Cuenta: Retiro de Javi');
});
