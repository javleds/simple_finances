import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import PrimeVue from 'primevue/config';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import AppInput from './AppInput.vue';
import AppSearchSelect from './AppSearchSelect.vue';
import AppDatePicker from './AppDatePicker.vue';
import AppModal from './AppModal.vue';
import AppPercentageSplitEditor from './AppPercentageSplitEditor.vue';

const wrappers: VueWrapper[] = [];
const global = { plugins: [PrimeVue] };

beforeEach(() => {
    vi.stubGlobal(
        'matchMedia',
        vi.fn(() => ({
            matches: false,
            addEventListener: vi.fn<() => void>(),
            removeEventListener: vi.fn<() => void>(),
        })),
    );
});

afterEach(() => {
    vi.unstubAllGlobals();
    wrappers.forEach((wrapper) => wrapper.unmount());
    wrappers.length = 0;
    document.body.innerHTML = '';
});

describe('PrimeVue shared controls', () => {
    it('preserves monetary strings during editing and formats only after blur', async () => {
        const wrapper = mount(AppInput, {
            props: { id: 'amount', mask: 'amount', modelValue: '1234.50' },
            global,
        });
        wrappers.push(wrapper);
        const input = wrapper.get('input');
        expect(input.element.value).toBe('$ 1,234.50');
        await input.trigger('focus');
        expect(input.element.value).toBe('1234.50');
        await input.setValue('0012,349');
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['12.34']);
        await input.trigger('blur');
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['12.34']);
        await input.trigger('focus');
        await input.setValue('');
        await input.trigger('blur');
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['']);
    });

    it('connects searchable selection labels and descriptions to its focusable control', async () => {
        const wrapper = mount(AppSearchSelect, {
            attachTo: document.body,
            props: {
                id: 'account',
                label: 'Cuenta',
                modelValue: null,
                options: [{ value: 'a', label: 'Principal' }],
                error: 'Selecciona una cuenta',
            },
            attrs: { name: 'account_id', required: true, 'aria-describedby': 'account-help' },
            global,
        });
        wrappers.push(wrapper);
        const control = wrapper.get('[role="combobox"]');
        const label = wrapper.get('label');
        expect(control.attributes('id')).toBe('account');
        expect(control.attributes('tabindex')).toBe('0');
        expect(label.attributes('for')).toBe(control.attributes('id'));
        expect(control.attributes('aria-labelledby')).toBe(label.attributes('id'));
        expect(control.attributes('aria-describedby')).toBe('account-help account-error');
        expect(wrapper.get('#account-error').text()).toBe('Selecciona una cuenta');
        expect(control.attributes('aria-required')).toBe('true');
        expect(wrapper.getComponent(Select).props('name')).toBe('account_id');
        await label.trigger('click');
        expect(document.activeElement).toBe(control.element);
        await wrapper.setProps({ error: undefined });
        expect(control.attributes('aria-describedby')).toBe('account-help');
    });

    it('keeps calendar days as local yyyy-MM-dd strings and clears to null', () => {
        const wrapper = mount(AppDatePicker, {
            props: { id: 'day', modelValue: '2026-10-09' },
            global,
        });
        wrappers.push(wrapper);
        const picker = wrapper.getComponent(DatePicker);
        const date = picker.props('modelValue') as Date;
        expect([date.getFullYear(), date.getMonth(), date.getDate()]).toEqual([2026, 9, 9]);
        picker.vm.$emit('update:modelValue', new Date(2026, 0, 2));
        expect(wrapper.emitted('update:modelValue')).toEqual([['2026-01-02']]);
        expect(wrapper.emitted('change')).toEqual([['2026-01-02']]);
        picker.vm.$emit('update:modelValue', null);
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null]);
    });

    it('redistributes exact percentages across members without losing basis points', async () => {
        const wrapper = mount(AppPercentageSplitEditor, {
            props: {
                users: [
                    { id: 'a', name: 'Ana' },
                    { id: 'b', name: 'Bea' },
                    { id: 'c', name: 'Cora' },
                ],
                modelValue: {},
            },
            global,
        });
        wrappers.push(wrapper);
        const initial = wrapper.emitted('update:modelValue')?.[0]?.[0] as Record<string, number>;
        expect(initial).toEqual({ a: 33.34, b: 33.33, c: 33.33 });
        await wrapper.setProps({ modelValue: initial });
        const input = wrapper.get('input#percentage-split-a');
        await input.trigger('focus');
        await input.setValue('70.01');
        await input.trigger('blur');
        const updated = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as Record<string, number>;
        expect(updated.a).toBe(70.01);
        expect(
            Object.values(updated).reduce((sum, value) => sum + Math.round(value * 100), 0),
        ).toBe(10000);
    });

    it('closes informational dialogs with a neutral action without submitting a form', async () => {
        const wrapper = mount(AppModal, {
            attachTo: document.body,
            props: { open: true, title: 'Información', presentation: 'sheet' },
            global,
        });
        wrappers.push(wrapper);
        await nextTick();
        const close = [...document.body.querySelectorAll<HTMLButtonElement>('button')].find(
            (button) => button.textContent?.trim() === 'Cerrar',
        )!;
        expect(close.type).toBe('button');
        expect(close.classList.contains('p-button-danger')).toBe(false);
        close.click();
        expect(wrapper.emitted('action')).toEqual([['close']]);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('submits an external form once and prevents pending actions', async () => {
        const form = document.createElement('form');
        form.id = 'external-form';
        document.body.appendChild(form);
        let submissions = 0;
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submissions += 1;
        });
        const wrapper = mount(AppModal, {
            attachTo: document.body,
            props: {
                open: true,
                title: 'Guardar',
                presentation: 'fullscreen',
                actions: [{ key: 'save', label: 'Guardar', type: 'submit', form: form.id }],
            },
            global,
        });
        wrappers.push(wrapper);
        await nextTick();
        const button = document.body.querySelector<HTMLButtonElement>(
            'button[form="external-form"]',
        )!;
        button.click();
        expect(submissions).toBe(1);
        expect(wrapper.emitted('action')).toEqual([['save']]);
        await wrapper.setProps({
            actions: [
                { key: 'save', label: 'Guardar', type: 'submit', form: form.id, loading: true },
            ],
        });
        button.click();
        expect(submissions).toBe(1);
        expect(wrapper.emitted('action')).toHaveLength(1);
        wrapper.getComponent(Dialog).vm.$emit('update:visible', false);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });
});
