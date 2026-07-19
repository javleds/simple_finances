import { computed, ref } from 'vue';

import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';

import { createTransactionsRepository } from '../repositories/transactionsRepository';
import type {
  CreatedTransactionResult,
  DeletedTransactionResult,
  Transaction,
  TransactionListFilters,
  TransactionMutationMeta,
  TransactionWritePayload,
} from '../types';

const transactionsRepository = createTransactionsRepository();

export function useTransactionsCrud() {
  const transactionsState = usePaginatedCollection<
    Transaction,
    [string | undefined, TransactionListFilters | undefined]
  >({
    defaultPerPage: 20,
    loadPage: async (options, accountId, filters) => {
      const response = await transactionsRepository.list(accountId, { ...options, filters });
      listMeta.value = response.meta;
      return response;
    },
    resolveErrorMessage: resolveApiErrorMessage,
    loadErrorMessage: 'No fue posible cargar las transacciones.',
    loadMoreErrorMessage: 'No fue posible cargar más transacciones.',
  });
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);
  const listMeta = ref<TransactionMutationMeta | null>(null);

  const hasTransactions = computed(() => transactionsState.hasItems.value);
  const hasMoreTransactions = computed(() => transactionsState.hasMoreItems.value);
  const hasReachedEnd = computed(() => transactionsState.hasReachedEnd.value);

  async function loadTransactions(
    accountId?: string,
    filters?: TransactionListFilters,
    options?: { reset?: boolean; perPage?: number },
  ): Promise<void> {
    await transactionsState.load([accountId, filters], options);
  }

  async function loadMoreTransactions(): Promise<void> {
    await transactionsState.loadMore();
  }

  async function createTransaction(
    payload: TransactionWritePayload,
  ): Promise<CreatedTransactionResult | null> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const result = await transactionsRepository.create(payload);
      transactionsState.prependItems(result.transactions);
      return result;
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible crear la transacción.');
      return null;
    } finally {
      isSaving.value = false;
    }
  }

  async function updateTransaction(
    transactionId: string,
    payload: TransactionWritePayload,
  ): Promise<CreatedTransactionResult | null> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const result = await transactionsRepository.update(transactionId, payload);
      transactionsState.replaceItem(
        (transaction) => transaction.id === transactionId,
        result.transaction,
      );
      return result;
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible actualizar la transacción.');
      return null;
    } finally {
      isSaving.value = false;
    }
  }

  async function deleteTransaction(
    transactionId: string,
    accountId?: string,
  ): Promise<DeletedTransactionResult | null> {
    isDeleting.value = true;
    deleteError.value = null;

    try {
      const result = await transactionsRepository.remove(transactionId, accountId);
      const removedIds = new Set([transactionId, ...result.meta.subtransactionIds]);
      transactionsState.removeItem((transaction) => removedIds.has(transaction.id));
      return result;
    } catch (error) {
      deleteError.value = resolveApiErrorMessage(error, 'No fue posible eliminar la transacción.');
      return null;
    } finally {
      isDeleting.value = false;
    }
  }

  function clearSaveError(): void {
    saveError.value = null;
  }

  function clearDeleteError(): void {
    deleteError.value = null;
  }

  return {
    transactions: transactionsState.items,
    hasTransactions,
    hasMoreTransactions,
    hasReachedEnd,
    isLoading: transactionsState.isLoading,
    isLoadingMore: transactionsState.isLoadingMore,
    isSaving,
    isDeleting,
    loadError: transactionsState.loadError,
    saveError,
    deleteError,
    listMeta,
    clearSaveError,
    clearDeleteError,
    loadTransactions,
    loadMoreTransactions,
    createTransaction,
    updateTransaction,
    deleteTransaction,
    perPage: transactionsState.perPage,
  };
}
