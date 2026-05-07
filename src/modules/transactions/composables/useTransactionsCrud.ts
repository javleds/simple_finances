import { computed, ref } from 'vue';

import { ApiError } from '@/lib/api/apiClient';

import { createTransactionsRepository } from '../repositories/transactionsRepository';
import type { Transaction, TransactionWritePayload } from '../types';

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
  const transactions = ref<Transaction[]>([]);
  const isLoading = ref(false);
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const loadError = ref<string | null>(null);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  const hasTransactions = computed(() => transactions.value.length > 0);

  async function loadTransactions(accountId?: string): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
      transactions.value = await transactionsRepository.list(accountId);
    } catch (error) {
      loadError.value = resolveErrorMessage(error, 'No fue posible cargar las transacciones.');
    } finally {
      isLoading.value = false;
    }
  }

  async function createTransaction(payload: TransactionWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const transaction = await transactionsRepository.create(payload);
      transactions.value = [transaction, ...transactions.value];
      return true;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible crear la transacción.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function updateTransaction(
    transactionId: string,
    payload: TransactionWritePayload,
  ): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const updatedTransaction = await transactionsRepository.update(transactionId, payload);
      transactions.value = transactions.value.map((transaction) =>
        transaction.id === transactionId ? updatedTransaction : transaction,
      );
      return true;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible actualizar la transacción.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function deleteTransaction(transactionId: string): Promise<boolean> {
    isDeleting.value = true;
    deleteError.value = null;

    try {
      await transactionsRepository.remove(transactionId);
      transactions.value = transactions.value.filter((transaction) => transaction.id !== transactionId);
      return true;
    } catch (error) {
      deleteError.value = resolveErrorMessage(error, 'No fue posible eliminar la transacción.');
      return false;
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
    transactions,
    hasTransactions,
    isLoading,
    isSaving,
    isDeleting,
    loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadTransactions,
    createTransaction,
    updateTransaction,
    deleteTransaction,
  };
}
