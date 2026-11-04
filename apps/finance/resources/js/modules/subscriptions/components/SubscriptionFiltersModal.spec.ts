import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { describe, expect, it } from 'vitest';
import SubscriptionFiltersModal from './SubscriptionFiltersModal.vue';
const panelStub = defineComponent({
    props: ['open'],
    emits: ['apply', 'clear', 'close'],
    template: `<div v-if="open"><form @submit.prevent="$emit('apply')"><slot /></form><button aria-label="Limpiar filtros" @click="$emit('clear')" /><button aria-label="Cerrar" @click="$emit('close')" /></div>`,
});
function renderModal() {
    return mount(SubscriptionFiltersModal, {
        props: {
            open: true,
            selectedStatuses: ['active'],
            selectedUnits: [],
            statusOptions: [
                { value: 'active', label: 'Activa' },
                { value: 'cancelled', label: 'Cancelada' },
            ],
            unitOptions: [{ value: 'months', label: 'Meses' }],
        },
        global: { stubs: { AppFilterPanel: panelStub } },
    });
}
describe('subscriptions filter drafts', () => {
    it('applies the full selection only when submitted', async () => {
        const wrapper = renderModal();
        await wrapper.get('input[value="months"]').setValue(true);
        expect(wrapper.emitted('apply')).toBeUndefined();
        await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('apply')).toEqual([[{ statuses: ['active'], units: ['months'] }]]);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });
    it('keeps the existing single-status selection behavior', async () => {
        const wrapper = renderModal();
        await wrapper.get('input[value="cancelled"]').setValue(true);
        expect((wrapper.get('input[value="active"]').element as HTMLInputElement).checked).toBe(
            false,
        );
        await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('apply')).toEqual([[{ statuses: ['cancelled'], units: [] }]]);
    });
    it('clears only the draft and restores applied values on reopening', async () => {
        const wrapper = renderModal();
        await wrapper.get('button[aria-label="Limpiar filtros"]').trigger('click');
        expect(wrapper.findAll('input:checked')).toHaveLength(0);
        expect(wrapper.emitted('apply')).toBeUndefined();
        await wrapper.get('button[aria-label="Cerrar"]').trigger('click');
        await wrapper.setProps({ open: false });
        await wrapper.setProps({ open: true });
        expect(wrapper.findAll('input:checked')).toHaveLength(1);
        expect(wrapper.emitted('apply')).toBeUndefined();
    });
});
