import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { computed, ref } from 'vue';

import { dashboardQueryKeys } from '@/modules/admin/queries/dashboardQueries';
import { transactionFacilityQueryKeys } from '@/modules/transactions/queries/transactionFacilityQueries';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';
import { accountQueryKeys } from '../queries/accountQueries';
import { createAccountsRepository } from '../repositories/accountsRepository';
import type {
  Account,
  AccountMemberTransferPayload,
  AccountMemberTransferResult,
  AccountPendingReimbursement,
} from '../types';

const accountsRepository = createAccountsRepository();

export function useAccountMemberTransfers() {
  const queryClient = useQueryClient();
  const transferError = ref<string | null>(null);
  const activeTransferKey = ref<string | null>(null);

  const transferMutation = useMutation({
    mutationFn: (payload: { accountId: string; transfer: AccountMemberTransferPayload }) =>
      accountsRepository.createMemberTransfer(payload.accountId, payload.transfer),
    onSuccess: (result, payload) => {
      updateAccountCache(payload.accountId, result);
      void queryClient.invalidateQueries({ queryKey: accountQueryKeys.all });
      void queryClient.invalidateQueries({ queryKey: dashboardQueryKeys.all });
      void queryClient.invalidateQueries({ queryKey: transactionFacilityQueryKeys.all });
    },
  });

  const isTransferring = computed(() => transferMutation.isPending.value);

  function transferKey(accountId: string, reimbursement: AccountPendingReimbursement): string {
    return `${accountId}:${reimbursement.fromUserId}:${reimbursement.toUserId}:${reimbursement.amount}`;
  }

  async function settleReimbursement(
    accountId: string,
    reimbursement: AccountPendingReimbursement,
  ): Promise<void> {
    const key = transferKey(accountId, reimbursement);

    transferError.value = null;
    activeTransferKey.value = key;

    try {
      await transferMutation.mutateAsync({
        accountId,
        transfer: {
          fromUserId: reimbursement.fromUserId,
          toUserId: reimbursement.toUserId,
          amount: reimbursement.amount,
          description: `Reembolso de ${reimbursement.fromUserName} a ${reimbursement.toUserName}`,
        },
      });
    } catch (error) {
      transferError.value = resolveApiErrorMessage(error, 'No fue posible registrar el reembolso.');
    } finally {
      activeTransferKey.value = null;
    }
  }

  async function settleAccountReimbursements(
    accountId: string,
    reimbursements: AccountPendingReimbursement[],
  ): Promise<void> {
    for (const reimbursement of reimbursements) {
      await settleReimbursement(accountId, reimbursement);
    }
  }

  async function settleAllAccountReimbursements(
    accounts: Array<{ id: string; pendingReimbursements: AccountPendingReimbursement[] }>,
  ): Promise<void> {
    for (const account of accounts) {
      await settleAccountReimbursements(account.id, account.pendingReimbursements);
    }
  }

  function updateAccountCache(accountId: string, result: AccountMemberTransferResult): void {
    const queryKey = accountQueryKeys.detail(accountId);
    const account = queryClient.getQueryData<Account>(queryKey);

    if (!account) {
      return;
    }

    queryClient.setQueryData<Account>(queryKey, {
      ...account,
      custodyByUser: result.custodyByUser,
      settlementsByUser: result.settlementsByUser,
      pendingReimbursements: result.pendingReimbursements,
      users: account.users.map((user) => {
        const custody = result.custodyByUser.find((item) => item.userId === user.id);
        const settlement = result.settlementsByUser.find((item) => item.userId === user.id);

        return {
          ...user,
          custodyAmount: custody?.amount ?? user.custodyAmount,
          settlementAmount: settlement?.amount ?? user.settlementAmount,
        };
      }),
    });
  }

  return {
    activeTransferKey,
    isTransferring,
    settleAllAccountReimbursements,
    settleAccountReimbursements,
    settleReimbursement,
    transferError,
    transferKey,
  };
}
