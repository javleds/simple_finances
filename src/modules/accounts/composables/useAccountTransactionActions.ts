import type { ComputedRef, Ref } from 'vue';

import type {
  Transaction,
  TransactionListFilters,
  TransactionMutationMeta,
  TransactionWritePayload,
} from '@/modules/transactions/types';

type LoadTransactionsOptions = {
  reset?: boolean;
  perPage?: number;
};

type TransactionMutationResult = {
  meta: TransactionMutationMeta;
};

type UseAccountTransactionActionsOptions = {
  accountId: ComputedRef<string>;
  activeFilters: ComputedRef<TransactionListFilters>;
  canCompleteTransaction: (transaction: Transaction) => boolean;
  closeCompleteTransactionModal: () => void;
  closeCreateTransactionModal: () => void;
  closeDeleteTransactionModal: () => void;
  closeEditTransactionModal: () => void;
  createTransaction: (payload: TransactionWritePayload) => Promise<TransactionMutationResult | null>;
  deleteTransaction: (transactionId: string, accountId?: string) => Promise<TransactionMutationResult | null>;
  hasReachedEnd: ComputedRef<boolean>;
  isLoadingMore: Ref<boolean>;
  loadMoreTransactions: () => Promise<void>;
  loadTransactions: (
    accountId: string,
    filters?: TransactionListFilters,
    options?: LoadTransactionsOptions,
  ) => Promise<void>;
  onMutationMeta: (meta: TransactionMutationMeta) => void;
  selectedTransaction: ComputedRef<Transaction | null>;
  transactionsPerPage: ComputedRef<number>;
  updateTransaction: (
    transactionId: string,
    payload: TransactionWritePayload,
  ) => Promise<TransactionMutationResult | null>;
};

export function useAccountTransactionActions(options: UseAccountTransactionActionsOptions) {
  async function handleTransactionSubmit(payload: TransactionWritePayload): Promise<void> {
    const result = await options.createTransaction(payload);

    if (!result) {
      return;
    }

    options.onMutationMeta(result.meta);
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

  async function reloadTransactions(): Promise<void> {
    if (!options.accountId.value) {
      return;
    }

    await options.loadTransactions(options.accountId.value, options.activeFilters.value, {
      reset: true,
      perPage: options.transactionsPerPage.value,
    });
  }

  function handleLoadMoreRetry(): void {
    void options.loadMoreTransactions();
  }

  function infiniteStatusLabel(): string {
    if (options.isLoadingMore.value) {
      return 'Cargando más transacciones...';
    }

    if (options.hasReachedEnd.value) {
      return 'Has llegado al final.';
    }

    return 'Sigue desplazándote para revisar más actividad conforme la cuenta acumule movimientos.';
  }

  return {
    confirmCompleteTransaction,
    confirmDeleteTransaction,
    handleEditTransactionSubmit,
    handleLoadMoreRetry,
    handleTransactionSubmit,
    infiniteStatusLabel,
    reloadTransactions,
  };
}
