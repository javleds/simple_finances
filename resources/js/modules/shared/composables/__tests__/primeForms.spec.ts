import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref, type Ref } from 'vue';
import { z } from 'zod';

import { useAccountForm } from '@/modules/accounts/composables/useAccountForm';
import { useRegisterForm } from '@/modules/auth/composables/useRegisterForm';
import { useTransactionForm } from '@/modules/transactions/composables/useTransactionForm';
import type { Transaction } from '@/modules/transactions/types';
import { useFormFieldInteraction } from '../useFormFieldInteraction';
import { usePrimeForm } from '../usePrimeForm';

const wrappers: Array<ReturnType<typeof mount>> = [];

function mountForm<T>(setup: () => T): T {
    let result: T;
    wrappers.push(
        mount(
            defineComponent({
                setup() {
                    result = setup();
                    return () => h('div');
                },
            }),
        ),
    );
    return result!;
}

afterEach(() => {
    wrappers.forEach((wrapper) => wrapper.unmount());
    wrappers.length = 0;
    document.body.innerHTML = '';
});

describe('PrimeVue Forms integration', () => {
    it('validates registration acceptance and cross-field confirmation, then submits parsed values', async () => {
        const { form, confirmation } = mountForm(() => ({
            form: useRegisterForm(),
            confirmation: useFormFieldInteraction('passwordConfirmation'),
        }));
        await flushPromises();
        expect(form.isSubmitDisabled.value).toBe(true);
        expect(confirmation.error.value).toBeUndefined();

        form.name.value = ' Test User ';
        form.email.value = 'test@example.com';
        form.password.value = 'password123';
        form.passwordConfirmation.value = 'different';
        form.termsAccepted.value = true;
        form.privacyPolicyAccepted.value = true;
        confirmation.touch();
        await flushPromises();
        expect(confirmation.error.value).toContain('coincidir');
        expect(await form.submitForm()).toBeUndefined();

        form.passwordConfirmation.value = 'password123';
        await flushPromises();
        expect(form.isSubmitDisabled.value).toBe(false);
        expect(confirmation.error.value).toBeUndefined();
        expect(await form.submitForm()).toMatchObject({
            name: 'Test User',
            email: 'test@example.com',
        });

        form.reset();
        await flushPromises();
        expect(form.name.value).toBe('');
        expect(confirmation.error.value).toBeUndefined();
        expect(form.isSubmitDisabled.value).toBe(true);
    });

    it('clears hidden credit fields and resets values and touched errors when editing another account', async () => {
        const initial = ref({ name: 'Credit', isCredit: true, creditLine: 3000, closingDay: 10 });
        const { form, nameError } = mountForm(() => ({
            form: useAccountForm({ initialValues: () => initial.value }),
            nameError: useFormFieldInteraction('name'),
        }));
        await flushPromises();
        expect(form.creditLine.value).toBe('3000');
        form.isCredit.value = 'no';
        await flushPromises();
        expect(form.creditLine.value).toBe('');
        expect(form.closingDay.value).toBe('');
        form.name.value = '';
        nameError.touch();
        await flushPromises();
        expect(nameError.error.value).toBeTruthy();

        initial.value = { name: 'Replacement', isCredit: false, creditLine: 0, closingDay: 0 };
        await flushPromises();
        expect(form.name.value).toBe('Replacement');
        expect(nameError.error.value).toBeUndefined();
    });

    it('preserves expense allocations, conditional payer/custodian checks, exact money and dates', async () => {
        const initial: Ref<Partial<Transaction>> = ref({
            concept: 'Dinner',
            amount: 123.45,
            date: '2026-10-09',
            type: 'expense',
            accountId: '10',
            paidByUserId: '1',
            userPayments: { '1': 50, '2': 50 },
        });
        const form = mountForm(() => useTransactionForm({ initialValues: () => initial.value }));
        await flushPromises();
        expect(await form.submitForm()).toMatchObject({
            amount: 123.45,
            date: '2026-10-09',
            userPayments: { '1': 50, '2': 50 },
        });
        form.userPayments.value = { '1': 60, '2': 30 };
        await flushPromises();
        expect(form.isSubmitDisabled.value).toBe(true);
        form.type.value = 'income';
        await flushPromises();
        expect(form.isSubmitDisabled.value).toBe(true);
        form.custodianUserId.value = '2';
        await flushPromises();
        expect(form.isSubmitDisabled.value).toBe(false);
        form.financialGoalId.value = 'goal';
        form.type.value = 'expense';
        await flushPromises();
        expect(form.financialGoalId.value).toBeNull();
        initial.value = {
            concept: 'New',
            amount: 0,
            date: '2026-11-01',
            accountId: '11',
            paidByUserId: '2',
        };
        await flushPromises();
        expect(form.amount.value).toBe('0');
        expect(form.date.value).toBe('2026-11-01');
        expect(await form.submitForm()).toMatchObject({
            amount: 0,
            accountId: '11',
            userPayments: {},
        });
    });

    it('submits through an external footer button and prevents concurrent submission', async () => {
        const submitted = vi.fn<(values: { amount: string }) => void>();
        let release: (() => void) | undefined;
        const pending = new Promise<void>((resolve) => {
            release = resolve;
        });
        let submit: () => Promise<void | undefined>;
        wrappers.push(
            mount(
                defineComponent({
                    setup() {
                        const form = usePrimeForm({
                            schema: z.object({ amount: z.string().min(1) }),
                            initialValues: { amount: '10.25' },
                        });
                        submit = form.handleSubmit(async (values) => {
                            submitted(values);
                            await pending;
                        });
                        return () =>
                            h('div', [
                                h('form', {
                                    id: 'external-prime-form',
                                    onSubmit: (event: Event) => {
                                        event.preventDefault();
                                        void submit();
                                    },
                                }),
                                h(
                                    'button',
                                    { type: 'submit', form: 'external-prime-form' },
                                    'Save',
                                ),
                            ]);
                    },
                }),
                { attachTo: document.body },
            ),
        );
        await flushPromises();
        document.querySelector<HTMLButtonElement>('button')!.click();
        document.querySelector<HTMLFormElement>('form')!.requestSubmit();
        await flushPromises();
        expect(submitted).toHaveBeenCalledExactlyOnceWith({ amount: '10.25' });
        release!();
        await flushPromises();
        await submit!();
        expect(submitted).toHaveBeenCalledTimes(2);
    });
});
