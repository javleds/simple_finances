import type { ComputedRef, Ref } from 'vue';

import type {
    Transaction,
    TransactionMutationMeta,
    TransactionSubmitOptions,
    TransactionWritePayload,
} from '@/modules/transactions/types';

type TransactionMutationResult = {
    meta: TransactionMutationMeta;
};

type UseAccountTransactionActionsOptions = {
    accountId: ComputedRef<string>;
    isSaving: Ref<boolean>;
    closeCreateTransactionModal: () => void;
    closeDeleteTransactionModal: () => void;
    closeEditTransactionModal: () => void;
    createTransaction: (
        payload: TransactionWritePayload,
    ) => Promise<TransactionMutationResult | null>;
    deleteTransaction: (
        transactionId: string,
        accountId?: string,
    ) => Promise<TransactionMutationResult | null>;
    onMutationMeta: (meta: TransactionMutationMeta) => void;
    prepareNextCreateTransaction: (payload: TransactionWritePayload) => void;
    selectedTransaction: ComputedRef<Transaction | null>;
    updateTransaction: (
        transactionId: string,
        payload: TransactionWritePayload,
    ) => Promise<TransactionMutationResult | null>;
};

export function useAccountTransactionActions(options: UseAccountTransactionActionsOptions) {
    async function handleTransactionSubmit(
        payload: TransactionWritePayload,
        submitOptions: TransactionSubmitOptions = { keepOpen: false },
    ): Promise<void> {
        if (options.isSaving.value) return;
        const result = await options.createTransaction(payload);

        if (!result) {
            return;
        }

        options.onMutationMeta(result.meta);

        if (submitOptions.keepOpen) {
            options.prepareNextCreateTransaction(payload);
            return;
        }

        options.closeCreateTransactionModal();
    }

    async function handleEditTransactionSubmit(payload: TransactionWritePayload): Promise<void> {
        if (options.isSaving.value) return;
        const selectedTransaction = options.selectedTransaction.value;

        if (!selectedTransaction) {
            return;
        }

        if (selectedTransaction.type !== payload.type && !confirmTypeChange()) {
            return;
        }

        const result = await options.updateTransaction(selectedTransaction.id, payload);

        if (!result) {
            return;
        }

        options.onMutationMeta(result.meta);
        options.closeEditTransactionModal();
    }

    async function confirmDeleteTransaction(): Promise<void> {
        if (!options.selectedTransaction.value) {
            return;
        }

        const result = await options.deleteTransaction(
            options.selectedTransaction.value.id,
            options.accountId.value,
        );

        if (!result) {
            return;
        }

        options.onMutationMeta(result.meta);
        options.closeDeleteTransactionModal();
    }

    function confirmTypeChange(): boolean {
        return window.confirm(
            'Cambiar el tipo recalculará balance, divisiones y saldos entre miembros. ¿Quieres guardar este cambio?',
        );
    }

    return {
        confirmDeleteTransaction,
        handleEditTransactionSubmit,
        handleTransactionSubmit,
    };
}
