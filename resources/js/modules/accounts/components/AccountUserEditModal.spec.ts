import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { describe, expect, it } from 'vitest';

import AccountUserEditModal from './AccountUserEditModal.vue';

const modalStub = defineComponent({
    emits: ['action'],
    template:
        "<div><slot /><button @click=\"$emit('action', 'submit-edit-user')\">Guardar</button></div>",
});
const inputStub = defineComponent({
    props: ['modelValue', 'error'],
    emits: ['update:modelValue'],
    template:
        '<div><input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /><span>{{ error }}</span></div>',
});

function renderModal(percentage: string) {
    return mount(AccountUserEditModal, {
        props: {
            open: true,
            selectedUser: null,
            actions: [],
            canSubmit: true,
            editPercentage: percentage,
        },
        global: { stubs: { AppModal: modalStub, AppInput: inputStub } },
    });
}

describe('member percentage form', () => {
    it('submits the initial percentage through the modal action', async () => {
        const wrapper = renderModal('25.50');
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(wrapper.emitted('save')).toHaveLength(1);
    });

    it('rejects an out of range percentage and allows correcting it', async () => {
        const wrapper = renderModal('120');
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(wrapper.emitted('save')).toBeUndefined();
        expect(wrapper.text()).toContain('El porcentaje debe estar entre 0 y 100.');
        await wrapper.get('input').setValue('100');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.emitted('save')).toHaveLength(1);
    });

    it('preserves the parent submission guard', async () => {
        const wrapper = renderModal('50');
        await wrapper.setProps({ canSubmit: false });
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.emitted('save')).toBeUndefined();
    });
    it('does not submit again while the modal action is pending', async () => {
        const wrapper = renderModal('50');
        await wrapper.setProps({
            actions: [{ key: 'submit-edit-user', label: 'Guardar', loading: true }],
        });
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.emitted('save')).toBeUndefined();
    });
});
