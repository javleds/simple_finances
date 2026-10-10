import { usePrimeForm } from '@/modules/shared/composables/usePrimeForm';
import { computed, toValue, watch } from 'vue';

import {
    createDefaultTransactionFormValues,
    mapTransactionFormToWritePayload,
    transactionFormSchema,
} from '../schemas/transactionSchemas';
import type { Transaction, TransactionFormValues, TransactionWritePayload } from '../types';

type UseTransactionFormOptions = {
    initialValues?: Partial<Transaction> | null | (() => Partial<Transaction> | null | undefined);
    lockedAccountId?: string | null;
};

export function useTransactionForm(options: UseTransactionFormOptions = {}) {
    const resolvedInitialValues = computed(() =>
        createDefaultTransactionFormValues(toValue(options.initialValues), options.lockedAccountId),
    );

    const { errors, handleSubmit, isSubmitting, meta, resetForm, setFieldValue, values } =
        usePrimeForm<TransactionFormValues>({
            schema: transactionFormSchema,
            initialValues: resolvedInitialValues.value,
        });

    watch(
        resolvedInitialValues,
        (nextValues) => {
            resetForm({
                values: nextValues,
            });
        },
        { deep: true },
    );

    const isIncome = computed(() => values.type === 'income');
    const isExpense = computed(() => values.type === 'expense');

    watch(isIncome, (nextIsIncome) => {
        if (!nextIsIncome) {
            setFieldValue('financialGoalId', null, false);
        }
    });

    const type = computed({
        get: () => values.type,
        set: (value: TransactionFormValues['type']) => setFieldValue('type', value, true),
    });

    const status = computed({
        get: () => values.status,
        set: (value: TransactionFormValues['status']) => setFieldValue('status', value, true),
    });

    const concept = computed({
        get: () => values.concept,
        set: (value: string) => setFieldValue('concept', value, true),
    });

    const amount = computed({
        get: () => values.amount,
        set: (value: string) => setFieldValue('amount', value, true),
    });

    const accountId = computed({
        get: () => values.accountId,
        set: (value: string | null) => setFieldValue('accountId', value, true),
    });

    const paidByUserId = computed({
        get: () => values.paidByUserId,
        set: (value: string | null) => setFieldValue('paidByUserId', value, true),
    });

    const custodianUserId = computed({
        get: () => values.custodianUserId,
        set: (value: string | null) => setFieldValue('custodianUserId', value, true),
    });

    const paymentSource = computed({
        get: () => values.paymentSource,
        set: (value: TransactionFormValues['paymentSource']) =>
            setFieldValue('paymentSource', value, true),
    });

    const splitBetweenUsers = computed({
        get: () => values.splitBetweenUsers,
        set: (value: boolean) => setFieldValue('splitBetweenUsers', value, true),
    });

    const date = computed({
        get: () => values.date,
        set: (value: string) => setFieldValue('date', value, true),
    });

    const financialGoalId = computed({
        get: () => values.financialGoalId,
        set: (value: string | null) => setFieldValue('financialGoalId', value, true),
    });

    const userPayments = computed({
        get: () => values.userPayments,
        set: (value: Record<string, number>) => setFieldValue('userPayments', value, true),
    });

    const isSubmitDisabled = computed(() => isSubmitting.value || !meta.value.valid);

    const submitForm = handleSubmit(
        (submittedValues): TransactionWritePayload =>
            mapTransactionFormToWritePayload(submittedValues),
    );

    return {
        type,
        status,
        concept,
        amount,
        accountId,
        paidByUserId,
        custodianUserId,
        paymentSource,
        splitBetweenUsers,
        date,
        financialGoalId,
        userPayments,
        errors,
        meta,
        isSubmitting,
        isSubmitDisabled,
        isIncome,
        isExpense,
        submitForm,
    };
}
