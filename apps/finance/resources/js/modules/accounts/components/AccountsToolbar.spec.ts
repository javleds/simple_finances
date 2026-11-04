import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { describe, expect, it, vi } from 'vitest';

import AccountsToolbar from './AccountsToolbar.vue';

const buttonStub = defineComponent({ template: '<button><slot /></button>' });
const sectionStub = defineComponent({ template: '<div><slot name="actions" /></div>' });

function renderToolbar(filterChips: { key: string; label: string; remove: () => void }[] = []) {
    return mount(AccountsToolbar, {
        props: { searchTerm: '', isFiltersOpen: false, filterChips },
        global: { stubs: { AppButton: buttonStub, AppSectionBar: sectionStub, AppInput: true } },
    });
}

describe('account filter toolbar', () => {
    it('exposes the create action by its accessible name', async () => {
        const wrapper = renderToolbar();
        await wrapper.get('button[aria-label="Crear cuenta"]').trigger('click');
        expect(wrapper.emitted('create')).toHaveLength(1);
    });

    it('shows the applied count and removes individual filters', async () => {
        const removeStatus = vi.fn();
        const removeKind = vi.fn();
        const wrapper = renderToolbar([
            { key: 'status-Activo', label: 'Activas', remove: removeStatus },
            { key: 'kind-credit', label: 'Crédito', remove: removeKind },
        ]);

        expect(wrapper.get('button[aria-controls="account-filters"]').text()).toBe('2');
        await wrapper.get('button[aria-label="Quitar filtro: Crédito"]').trigger('click');
        expect(removeKind).toHaveBeenCalledOnce();
        expect(removeStatus).not.toHaveBeenCalled();

        await wrapper.setProps({
            filterChips: [{ key: 'status-Activo', label: 'Activas', remove: removeStatus }],
        });
        expect(wrapper.get('button[aria-controls="account-filters"]').text()).toBe('1');
        expect(wrapper.find('button[aria-label="Quitar filtro: Crédito"]').exists()).toBe(false);
    });

    it('opens the panel and exposes its expanded state without showing an empty chip area', async () => {
        const wrapper = renderToolbar();
        const trigger = wrapper.get('button[aria-controls="account-filters"]');
        expect(trigger.text()).toBe('');
        expect(trigger.attributes('aria-label')).toBe('Abrir filtros');
        expect(trigger.attributes('aria-expanded')).toBe('false');
        expect(wrapper.find('[aria-label="Filtros aplicados"]').exists()).toBe(false);

        await trigger.trigger('click');
        expect(wrapper.emitted('openFilters')).toHaveLength(1);
        await wrapper.setProps({ isFiltersOpen: true });
        expect(trigger.attributes('aria-expanded')).toBe('true');
    });
});
