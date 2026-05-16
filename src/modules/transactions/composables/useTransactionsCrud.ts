import { computed, ref } from 'vue';

import { ApiError } from '@/lib/api/apiClient';
import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';

import { createTransactionsRepository } from '../repositories/transactionsRepository';
import type {
  CreatedTransactionResult,
  DeletedTransactionResult,
  Transaction,
  TransactionWritePayload,
} from '../types';

const transactionsRepository = createTransactionsRepository();

function resolveErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

export function useTransactionsCrud() {
  const transactionsState = usePaginatedCollection<Transaction, [string?]>({
    defaultPerPage: 20,
    loadPage: (options, accountId) => transactionsRepository.list(accountId, options),
    resolveErrorMessage,
    loadErrorMessage: 'No fue posible cargar las transacciones.',
    loadMoreErrorMessage: 'No fue posible cargar más transacciones.',
  });
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  const hasTransactions = computed(() => transactionsState.hasItems.value);
  const hasMoreTransactions = computed(() => transactionsState.hasMoreItems.value);
  const hasReachedEnd = computed(() => transactionsState.hasReachedEnd.value);

  async function loadTransactions(accountId?: string, options?: { reset?: boolean; perPage?: number }): Promise<void> {
    await transactionsState.load([accountId], options);
  }

  async function loadMoreTransactions(): Promise<void> {
    await transactionsState.loadMore();
  }

  async function createTransaction(payload: TransactionWritePayload): Promise<CreatedTransactionResult | null> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const result = await transactionsRepository.create(payload);
      transactionsState.prependItem(result.transaction);
      return result;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible crear la transacción.');
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
      saveError.value = resolveErrorMessage(error, 'No fue posible actualizar la transacción.');
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
      transactionsState.removeItem((transaction) => transaction.id === transactionId);
      return result;
    } catch (error) {
      deleteError.value = resolveErrorMessage(error, 'No fue posible eliminar la transacción.');
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
