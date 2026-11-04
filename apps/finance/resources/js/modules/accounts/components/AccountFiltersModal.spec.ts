import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { describe, expect, it } from 'vitest';

import AccountFiltersModal from './AccountFiltersModal.vue';

const modalStub = defineComponent({
    props: ['open'],
    emits: ['close'],
    template:
        '<div v-if="open"><button aria-label="Cerrar" @click="$emit(\'close\')">Cerrar</button><slot /><slot name="footer" /></div>',
});
const buttonStub = defineComponent({
    template: '<button type="button"><slot /></button>',
});

function renderModal() {
    return mount(AccountFiltersModal, {
        props: {
            open: true,
            selectedStatuses: ['Activo'],
            selectedKinds: [],
            selectedSurfaces: [],
            statusOptions: ['Activo', 'Inactivo'],
            kindOptions: [
                { value: 'debit', label: 'Débito' },
                { value: 'credit', label: 'Crédito' },
            ],
            surfaceOptions: [
                { value: 'physical', label: 'Física' },
                { value: 'virtual', label: 'Virtual' },
            ],
        },
        global: { stubs: { AppModal: modalStub, AppButton: buttonStub } },
    });
}

describe('account filter drafts', () => {
    it('applies all selected groups together only after submitting', async () => {
        const wrapper = renderModal();
        await wrapper.get('input[value="credit"]').setValue(true);
        await wrapper.get('input[value="virtual"]').setValue(true);

        expect(wrapper.emitted('apply')).toBeUndefined();
        expect(wrapper.emitted('close')).toBeUndefined();

        await wrapper.get('form').trigger('submit');

        expect(wrapper.emitted('apply')).toEqual([
            [
                {
                    statuses: ['Activo'],
                    kinds: ['credit'],
                    surfaces: ['virtual'],
                },
            ],
        ]);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('clears only the draft until the empty selection is applied', async () => {
        const wrapper = renderModal();
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Limpiar filtros')!
            .trigger('click');

        expect(wrapper.findAll('input:checked')).toHaveLength(0);
        expect(wrapper.emitted('apply')).toBeUndefined();
        expect(wrapper.props('selectedStatuses')).toEqual(['Activo']);

        await wrapper.get('form').trigger('submit');

        expect(wrapper.emitted('apply')).toEqual([[{ statuses: [], kinds: [], surfaces: [] }]]);
    });

    it('discards pending edits when closed and reopened', async () => {
        const wrapper = renderModal();
        await wrapper.get('input[value="credit"]').setValue(true);
        await wrapper.get('button[aria-label="Cerrar"]').trigger('click');
        expect(wrapper.emitted('apply')).toBeUndefined();
        expect(wrapper.emitted('close')).toHaveLength(1);

        await wrapper.setProps({ open: false });
        await wrapper.setProps({ open: true });

        expect((wrapper.get('input[value="credit"]').element as HTMLInputElement).checked).toBe(
            false,
        );
        expect((wrapper.get('input[value="Activo"]').element as HTMLInputElement).checked).toBe(
            true,
        );
        await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('apply')).toEqual([
            [{ statuses: ['Activo'], kinds: [], surfaces: [] }],
        ]);
    });
});
