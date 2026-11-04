import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { describe, expect, it } from 'vitest';
import DistributionRuleFiltersModal from './DistributionRuleFiltersModal.vue';
const panelStub = defineComponent({
    props: ['open'],
    emits: ['apply', 'clear', 'close'],
    template: `<div v-if="open"><form @submit.prevent="$emit('apply')"><slot /></form><button aria-label="Limpiar filtros" @click="$emit('clear')" /><button aria-label="Cerrar" @click="$emit('close')" /></div>`,
});
function renderModal() {
    return mount(DistributionRuleFiltersModal, {
        props: {
            open: true,
            selectedFrequencies: ['monthly'],
            frequencyOptions: [
                { value: 'monthly', label: 'Mensual' },
                { value: 'semi_monthly', label: 'Quincenal' },
            ],
        },
        global: { stubs: { AppFilterPanel: panelStub } },
    });
}
describe('distribution filter drafts', () => {
    it('applies the full selection only when submitted', async () => {
        const wrapper = renderModal();
        await wrapper.get('input[value="semi_monthly"]').setValue(true);
        expect(wrapper.emitted('apply')).toBeUndefined();
        await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('apply')).toEqual([[{ frequencies: ['monthly', 'semi_monthly'] }]]);
        expect(wrapper.emitted('close')).toHaveLength(1);
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
