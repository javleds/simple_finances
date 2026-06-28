import type { ComputedRef } from 'vue';

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
  canCompleteTransaction: (transaction: Transaction) => boolean;
  closeCompleteTransactionModal: () => void;
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
    if (!options.selectedTransaction.value) {
      return;
    }

    const result = await options.updateTransaction(options.selectedTransaction.value.id, payload);

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

  async function confirmCompleteTransaction(): Promise<void> {
    const selectedTransaction = options.selectedTransaction.value;

    if (!selectedTransaction || !options.canCompleteTransaction(selectedTransaction)) {
      return;
    }

    const result = await options.updateTransaction(selectedTransaction.id, {
      type: selectedTransaction.type,
      status: 'completed',
      concept: selectedTransaction.concept,
      amount: selectedTransaction.amount,
      accountId: selectedTransaction.accountId,
      splitBetweenUsers: Object.keys(selectedTransaction.userPayments).length > 0,
      date: selectedTransaction.date,
      financialGoalId: selectedTransaction.financialGoalId,
      userPayments: selectedTransaction.userPayments,
    });

    if (!result) {
      return;
    }

    options.onMutationMeta(result.meta);
    options.closeCompleteTransactionModal();
  }

  return {
    confirmCompleteTransaction,
    confirmDeleteTransaction,
    handleEditTransactionSubmit,
    handleTransactionSubmit,
  };
}
