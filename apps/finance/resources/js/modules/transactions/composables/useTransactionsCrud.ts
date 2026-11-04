import { personalMoneyQueryKeys } from '@/modules/personal-money/composables/usePersonalMoney';
import { useQueryClient } from '@tanstack/vue-query';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';

import { categoryQueryKeys } from '@/modules/categories/composables/useCategories';
import { dashboardQueryKeys } from '@/modules/admin/queries/dashboardQueries';
import { accountQueryKeys } from '@/modules/accounts/queries/accountQueries';
import type { Account } from '@/modules/accounts/types';
import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';
import { transactionFacilityQueryKeys } from '@/modules/transactions/queries/transactionFacilityQueries';

import { transactionAttachmentsRepository } from '../repositories/transactionAttachmentsRepository';
import { createTransactionsRepository } from '../repositories/transactionsRepository';
import type {
    AttachmentDraft,
    CreatedTransactionResult,
    DeletedTransactionResult,
    Transaction,
    TransactionListFilters,
    TransactionMutationMeta,
    TransactionWritePayload,
} from '../types';

const transactionsRepository = createTransactionsRepository();

export function useTransactionsCrud() {
    const queryClient = useQueryClient();
    const savedDrafts = new WeakMap<AttachmentDraft, CreatedTransactionResult>();
    async function uploadAttachments(
        payload: TransactionWritePayload,
        result: CreatedTransactionResult,
    ): Promise<void> {
        const draft = payload.attachments;
        if (!draft || draft.files.length === 0) return;
        draft.transactionId = result.transaction.id;
        savedDrafts.set(draft, result);
        for (const entry of draft.files) {
            if (entry.uploaded) continue;
            await transactionAttachmentsRepository.upload(
                result.transaction.id,
                entry.file,
                entry.uploadId,
            );
            entry.uploaded = true;
            result.transaction.attachmentsCount = (result.transaction.attachmentsCount ?? 0) + 1;
            transactionsState.replaceItem(
                (transaction) => transaction.id === result.transaction.id,
                { ...result.transaction },
            );
            void queryClient.invalidateQueries({ queryKey: transactionFacilityQueryKeys.all });
            await queryClient.invalidateQueries({
                queryKey: ['transaction-attachments', result.transaction.id],
            });
        }
    }
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
    function syncAttachmentCount(event: Event): void {
        const detail = (event as CustomEvent<{ transactionId: string; count: number }>).detail;
        const transaction = transactionsState.items.value.find(
            (item) => item.id === detail.transactionId,
        );
        if (transaction)
            transactionsState.replaceItem((item) => item.id === detail.transactionId, {
                ...transaction,
                attachmentsCount: detail.count,
            });
    }
    onMounted(() =>
        window.addEventListener('transaction-attachments-count-updated', syncAttachmentCount),
    );
    onBeforeUnmount(() =>
        window.removeEventListener('transaction-attachments-count-updated', syncAttachmentCount),
    );
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
        if (isSaving.value) return null;
        isSaving.value = true;
        saveError.value = null;

        try {
            const previous = payload.attachments?.transactionId
                ? savedDrafts.get(payload.attachments)
                : undefined;
            const result = previous ?? (await transactionsRepository.create(payload));
            if (!previous) {
                transactionsState.prependItems(result.transactions);
                syncAccountState(payload.accountId, result.meta);
            }
            await uploadAttachments(payload, result);
            if (payload.attachments) savedDrafts.delete(payload.attachments);
            return result;
        } catch (error) {
            saveError.value = payload.attachments?.transactionId
                ? `El movimiento se guardó, pero faltan comprobantes por subir. ${resolveApiErrorMessage(error, 'Reintenta o quita los archivos pendientes para continuar.')}`
                : resolveApiErrorMessage(error, 'No fue posible crear la transacción.');
            return null;
        } finally {
            isSaving.value = false;
        }
    }

    async function updateTransaction(
        transactionId: string,
        payload: TransactionWritePayload,
    ): Promise<CreatedTransactionResult | null> {
        if (isSaving.value) return null;
        isSaving.value = true;
        saveError.value = null;

        try {
            const previous = payload.attachments?.transactionId
                ? savedDrafts.get(payload.attachments)
                : undefined;
            const result =
                previous ?? (await transactionsRepository.update(transactionId, payload));
            transactionsState.replaceItem(
                (transaction) => transaction.id === transactionId,
                result.transaction,
            );
            if (!previous) syncAccountState(payload.accountId, result.meta);
            await uploadAttachments(payload, result);
            if (payload.attachments) savedDrafts.delete(payload.attachments);
            return result;
        } catch (error) {
            saveError.value = resolveApiErrorMessage(
                error,
                payload.attachments?.transactionId
                    ? 'El movimiento se guardó, pero faltan comprobantes. Reintenta o quita los archivos pendientes.'
                    : 'No fue posible actualizar la transacción.',
            );
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
            if (accountId) {
                syncAccountState(accountId, result.meta);
            }
            if (!accountId) {
                void queryClient.invalidateQueries({ queryKey: categoryQueryKeys.all });
                void queryClient.invalidateQueries({ queryKey: personalMoneyQueryKeys.all });
            }
            return result;
        } catch (error) {
            deleteError.value = resolveApiErrorMessage(
                error,
                'No fue posible eliminar la transacción.',
            );
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

    function syncAccountState(accountId: string, meta: TransactionMutationMeta): void {
        updateAccountDetailCache(accountId, meta);
        notifyLedgerRows(accountId, meta);
        void queryClient.invalidateQueries({ queryKey: categoryQueryKeys.all });
        void queryClient.invalidateQueries({ queryKey: accountQueryKeys.all });
        void queryClient.invalidateQueries({ queryKey: dashboardQueryKeys.all });
        void queryClient.invalidateQueries({ queryKey: personalMoneyQueryKeys.all });
        void queryClient.invalidateQueries({ queryKey: transactionFacilityQueryKeys.all });
    }

    function notifyLedgerRows(accountId: string, meta: TransactionMutationMeta): void {
        if (!meta.ledgerRows || typeof window === 'undefined') {
            return;
        }

        window.dispatchEvent(
            new CustomEvent('account-ledger-rows-updated', {
                detail: {
                    accountId,
                    rows: meta.ledgerRows,
                },
            }),
        );
    }

    function updateAccountDetailCache(accountId: string, meta: TransactionMutationMeta): void {
        const account = queryClient.getQueryData<Account>(accountQueryKeys.detail(accountId));

        if (!account) {
            return;
        }

        queryClient.setQueryData<Account>(accountQueryKeys.detail(accountId), {
            ...account,
            balance: meta.accountBalance ?? account.balance,
            custodyByUser: meta.custodyByUser ?? account.custodyByUser,
            settlementsByUser: meta.settlementsByUser ?? account.settlementsByUser,
            pendingReimbursements: meta.pendingReimbursements ?? account.pendingReimbursements,
            users: account.users.map((user) => {
                const custody = meta.custodyByUser?.find((item) => item.userId === user.id);
                const settlement = meta.settlementsByUser?.find((item) => item.userId === user.id);

                return {
                    ...user,
                    custodyAmount: custody?.amount ?? user.custodyAmount,
                    settlementAmount: settlement?.amount ?? user.settlementAmount,
                };
            }),
        });
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
