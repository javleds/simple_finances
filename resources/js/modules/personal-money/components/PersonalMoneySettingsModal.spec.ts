import { flushPromises, mount } from '@vue/test-utils';
import { ref } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PersonalMoneySettingsModal from './PersonalMoneySettingsModal.vue';
import type { PersonalMoneyState } from '../types';

const mocks = vi.hoisted(() => ({ saveSettings: vi.fn() }));
const state = ref<PersonalMoneyState>();
const isSaving = ref(false);
vi.mock('../composables/usePersonalMoney', () => ({
    usePersonalMoney: () => ({ state, isSaving, mutationError: ref(null), ...mocks }),
}));
const wrappers: ReturnType<typeof mount>[] = [];
afterEach(() => wrappers.splice(0).forEach((wrapper) => wrapper.unmount()));
beforeEach(() => {
    vi.clearAllMocks();
    mocks.saveSettings.mockResolvedValue({});
    isSaving.value = false;
    state.value = {
        settings: { account_id: 1, started_at: '2020-01-01', opening_balance: 100, enabled: true },
        accounts: [{ id: 1, name: 'Personal' }],
        available_balance: 100,
        pending_count: 0,
        history: [],
        meta: { current_page: 1, last_page: 1, total: 0 },
    };
});
async function setup() {
    const wrapper = mount(PersonalMoneySettingsModal, {
        props: { open: true },
        global: {
            stubs: {
                AppModal: {
                    name: 'AppModal',
                    props: ['open', 'actions'],
                    template: '<div v-if="open"><slot /></div>',
                },
                AppText: { template: '<p><slot /></p>' },
                AppSearchSelect: { props: ['modelValue'], template: '<div />' },
                AppDatePicker: { props: ['modelValue'], template: '<div />' },
                AppInput: { name: 'AppInput', props: ['modelValue'], template: '<div />' },
            },
        },
    });
    wrappers.push(wrapper);
    await flushPromises();
    return wrapper;
}

describe('personal money settings modal', () => {
    it('loads current settings and saves an updated opening balance', async () => {
        const wrapper = await setup();
        const input = wrapper.findComponent({ name: 'AppInput' });
        expect(input.props('modelValue')).toBe('100');
        input.vm.$emit('update:modelValue', '250.50');
        await flushPromises();
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(mocks.saveSettings).toHaveBeenCalledWith({
            account_id: 1,
            started_at: '2020-01-01',
            opening_balance: 250.5,
            enabled: true,
        });
        expect(wrapper.emitted('close')).toHaveLength(1);
    });
    it('requires a personal account before activation', async () => {
        state.value!.settings = null;
        state.value!.accounts = [];
        const wrapper = await setup();
        expect(wrapper.text()).toContain('Primero crea una cuenta individual');
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(mocks.saveSettings).not.toHaveBeenCalled();
    });
    it('disables the calculation while preserving the existing configuration', async () => {
        const wrapper = await setup();
        wrapper.findComponent({ name: 'AppModal' }).vm.$emit('action', 'disable');
        await flushPromises();
        expect(mocks.saveSettings).toHaveBeenCalledWith({
            ...state.value!.settings,
            enabled: false,
        });
        expect(wrapper.emitted('close')).toHaveLength(1);
    });
    it('blocks submissions and closing while a save is in progress', async () => {
        const wrapper = await setup();
        isSaving.value = true;
        await wrapper.find('form').trigger('submit');
        wrapper.findComponent({ name: 'AppModal' }).vm.$emit('close');
        wrapper.findComponent({ name: 'AppModal' }).vm.$emit('action', 'disable');
        await flushPromises();
        expect(mocks.saveSettings).not.toHaveBeenCalled();
        expect(wrapper.emitted('close')).toBeUndefined();
    });
    it('keeps the modal open after a failed save', async () => {
        mocks.saveSettings.mockRejectedValue(new Error('Unavailable'));
        const wrapper = await setup();
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(mocks.saveSettings).toHaveBeenCalledOnce();
        expect(wrapper.emitted('close')).toBeUndefined();
    });
});
