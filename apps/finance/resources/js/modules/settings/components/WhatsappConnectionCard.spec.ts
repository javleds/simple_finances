import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import PrimeVue from 'primevue/config';
import { setStoredAuthSession } from '@/modules/auth/lib/authSession';

import WhatsappConnectionCard from './WhatsappConnectionCard.vue';
import type { WhatsappConnection } from '../repositories/whatsappConnectionRepository';

const repository = vi.hoisted(() => ({
    get: vi.fn<(...args: string[]) => Promise<WhatsappConnection>>(),
    requestCode: vi.fn<(...args: string[]) => Promise<WhatsappConnection>>(),
    verify: vi.fn<(...args: string[]) => Promise<WhatsappConnection>>(),
    unlink: vi.fn<(...args: string[]) => Promise<WhatsappConnection>>(),
}));

vi.mock('../repositories/whatsappConnectionRepository', () => ({
    createWhatsappConnectionRepository: () => repository,
}));

const unlinked: WhatsappConnection = {
    status: 'unlinked',
    phone_number: null,
    expires_at: null,
    resend_available_at: null,
};
const linked: WhatsappConnection = {
    ...unlinked,
    status: 'linked',
    phone_number: '+525512345678',
};

function pending(): WhatsappConnection {
    return {
        status: 'pending',
        phone_number: '+525512345678',
        expires_at: new Date(Date.now() + 86_400_000).toISOString(),
        resend_available_at: new Date(Date.now() + 60_000).toISOString(),
    };
}

let wrapper: VueWrapper;
let queryClient: QueryClient;

async function render(state: WhatsappConnection = unlinked): Promise<void> {
    repository.get.mockResolvedValue(state);
    queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    wrapper = mount(WhatsappConnectionCard, {
        attachTo: document.body,
        global: {
            stubs: { teleport: true },
            plugins: [
                [VueQueryPlugin, { queryClient }],
                [PrimeVue, { unstyled: true }],
            ],
        },
    });
    await flushPromises();
}

beforeEach(() => {
    vi.resetAllMocks();
    window.localStorage.clear();
});
afterEach(() => {
    wrapper?.unmount();
    queryClient?.clear();
    document.body.innerHTML = '';
});

describe('WhatsApp connection settings', () => {
    it('never displays another user connection after switching authenticated sessions', async () => {
        function switchUser(id: string): void {
            setStoredAuthSession({
                user: {
                    id,
                    name: 'Test',
                    email: `${id}@example.com`,
                    isEmailVerified: true,
                    phoneNumber: null,
                    emailVerifiedAt: null,
                    telegramChatId: null,
                },
                token: `token-${id}`,
                tokenType: 'bearer',
                expiresAt: '',
                postAuthRedirect: null,
            });
        }

        switchUser('1');
        await render(linked);
        expect(wrapper.text()).toContain('+525512345678');
        wrapper.unmount();
        switchUser('2');
        repository.get.mockResolvedValue(unlinked);
        wrapper = mount(WhatsappConnectionCard, {
            global: {
                plugins: [
                    [VueQueryPlugin, { queryClient }],
                    [PrimeVue, { unstyled: true }],
                ],
            },
        });
        expect(wrapper.text()).not.toContain('+525512345678');
        await flushPromises();

        expect(repository.get).toHaveBeenCalledTimes(2);
        expect(wrapper.text()).toContain('Ligar cuenta con WhatsApp');
        expect(wrapper.text()).not.toContain('+525512345678');
    });

    it('closes the phone sheet without requesting a code', async () => {
        await render();
        await wrapper.get('button').trigger('click');
        await wrapper.get('#whatsapp-phone').setValue('5512345678');
        const cancel = wrapper.findAll('button').find((button) => button.text() === 'Cancelar');
        await cancel?.trigger('click');
        await flushPromises();

        expect(repository.requestCode).not.toHaveBeenCalled();
        expect(wrapper.find('#whatsapp-phone').exists()).toBe(false);
        expect(wrapper.text()).toContain('Ligar cuenta con WhatsApp');
    });

    it('requests a code with the Mexican prefix and refreshes server state', async () => {
        await render();
        await wrapper.get('button').trigger('click');
        await wrapper.get('#whatsapp-phone').setValue('5512345678');
        const next = pending();
        repository.requestCode.mockResolvedValue(next);
        repository.get.mockResolvedValue(next);

        const send = wrapper.findAll('button').find((button) => button.text() === 'Enviar código');
        await send?.trigger('click');
        await flushPromises();

        expect(repository.requestCode).toHaveBeenCalledWith('+525512345678');
        expect(repository.get).toHaveBeenCalledTimes(2);
        expect(wrapper.text()).toContain('Enviamos un código a +525512345678');
        expect(wrapper.find('#whatsapp-phone').exists()).toBe(false);
    });

    it('rejects invalid phone numbers before sending', async () => {
        await render();
        await wrapper.get('button').trigger('click');
        await wrapper.get('#whatsapp-phone').setValue('123');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(repository.requestCode).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toContain('diez dígitos');
    });

    it('restores pending verification and verifies a pasted code with a leading zero', async () => {
        await render(pending());
        expect(wrapper.text()).toContain('Enviamos un código');
        expect(wrapper.findAll('input')).toHaveLength(6);
        await wrapper.get('input').trigger('paste', {
            clipboardData: { getData: () => '012345' },
        });
        repository.verify.mockResolvedValue(linked);
        repository.get.mockResolvedValue(linked);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(repository.verify).toHaveBeenCalledWith('012345');
        expect(wrapper.text()).toContain('Número vinculado: +525512345678');
    });

    it('refreshes pending state when failed verification exhausts the code', async () => {
        await render(pending());
        await wrapper.get('input').trigger('paste', {
            clipboardData: { getData: () => '012345' },
        });
        repository.verify.mockRejectedValue(new Error('Solicita otro código.'));
        repository.get.mockResolvedValue(unlinked);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(repository.get).toHaveBeenCalledTimes(2);
        expect(wrapper.get('[role="alert"]').text()).toContain('Solicita otro código.');
        expect(wrapper.text()).toContain('Ligar cuenta con WhatsApp');
        expect(wrapper.findAll('input')).toHaveLength(0);
    });

    it('disables resending until the cooldown has elapsed', async () => {
        await render(pending());
        const resend = wrapper
            .findAll('button')
            .find((button) => button.text().startsWith('Reenviar'));
        expect(resend?.attributes('disabled')).toBeDefined();
        expect(repository.requestCode).not.toHaveBeenCalled();
    });

    it('disables verification for an expired code and permits a new code', async () => {
        await render({
            ...pending(),
            expires_at: new Date(Date.now() - 1000).toISOString(),
            resend_available_at: new Date(Date.now() - 1000).toISOString(),
        });
        expect(wrapper.text()).toContain('El código expiró');
        expect(wrapper.get('input').attributes('disabled')).toBeDefined();
        const resend = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Reenviar código');
        repository.requestCode.mockResolvedValue(pending());
        repository.get.mockResolvedValue(pending());
        await resend?.trigger('click');
        await flushPromises();

        expect(repository.requestCode).toHaveBeenCalledWith('+525512345678');
    });

    it('shows send errors while retaining the phone form', async () => {
        await render();
        await wrapper.get('button').trigger('click');
        await wrapper.get('#whatsapp-phone').setValue('5512345678');
        repository.requestCode.mockRejectedValue(new Error('No fue posible enviar el código.'));
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('No fue posible enviar');
        expect(wrapper.get('#whatsapp-phone').element).toHaveProperty('value', '5512345678');
    });

    it('requires confirmation to unlink and refreshes the displayed state', async () => {
        await render(linked);
        await wrapper.get('button').trigger('click');
        expect(repository.unlink).not.toHaveBeenCalled();
        expect(document.querySelector('[role="dialog"]')?.textContent).toContain('+525512345678');
        repository.unlink.mockResolvedValue(unlinked);
        repository.get.mockResolvedValue(unlinked);
        const confirm = [...document.querySelectorAll('button')].find(
            (button) => button.textContent?.trim() === 'Desvincular',
        );
        confirm?.click();
        await flushPromises();

        expect(repository.unlink).toHaveBeenCalledOnce();
        expect(wrapper.text()).toContain('Ligar cuenta con WhatsApp');
        expect(document.querySelector('[role="dialog"]')).toBeNull();
    });

    it('provides retry when loading fails', async () => {
        repository.get.mockRejectedValue(new Error('No fue posible cargar WhatsApp.'));
        queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
        wrapper = mount(WhatsappConnectionCard, {
            global: {
                plugins: [
                    [VueQueryPlugin, { queryClient }],
                    [PrimeVue, { unstyled: true }],
                ],
            },
        });
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('No fue posible cargar WhatsApp.');
        repository.get.mockResolvedValue(unlinked);
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Ligar cuenta con WhatsApp');
    });
    it('discards the previous code when resending creates a new challenge', async () => {
        const initial = {
            ...pending(),
            resend_available_at: new Date(Date.now() - 1000).toISOString(),
        };
        await render(initial);
        await wrapper.get('input').trigger('paste', {
            clipboardData: { getData: () => '012345' },
        });
        const next = { ...pending(), expires_at: new Date(Date.now() + 172_800_000).toISOString() };
        repository.requestCode.mockResolvedValue(next);
        repository.get.mockResolvedValue(next);
        const resend = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Reenviar código');
        await resend?.trigger('click');
        await flushPromises();
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(repository.verify).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toContain('seis dígitos');
    });
});
