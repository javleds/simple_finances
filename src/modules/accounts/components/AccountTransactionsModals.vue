<script setup lang="ts">
import type { AppModalAction } from '@/modules/shared/types/modal';
import AccountTransactionDeleteModal from '@/modules/accounts/components/AccountTransactionDeleteModal.vue';
import AccountTransactionFormModal from '@/modules/accounts/components/AccountTransactionFormModal.vue';
import type { AccountMember } from '@/modules/accounts/types';
import type { AccountGoal } from '@/modules/accounts/schemas/accountGoalSchemas';
import type {
  Transaction,
  TransactionSubmitOptions,
  TransactionWritePayload,
} from '@/modules/transactions/types';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
  keepOpen: boolean;
};

const props = withDefaults(
  defineProps<{
    accountId: string;
    accountUsers: AccountMember[];
    accountBalance?: number | null;
    createTransactionActions: ReadonlyArray<AppModalAction>;
    deleteError?: string | null;
    deleteTransactionActions: ReadonlyArray<AppModalAction>;
    editTransactionActions: ReadonlyArray<AppModalAction>;
    financialGoals: AccountGoal[];
    createInitialValues?: Partial<Transaction> | null;
    isCreateTransactionModalOpen: boolean;
    isDeleteTransactionModalOpen: boolean;
    isEditTransactionModalOpen: boolean;
    isLoadingFinancialGoals: boolean;
    saveError?: string | null;
    selectedTransaction: Transaction | null;
  }>(),
  {
    createInitialValues: null,
    accountBalance: null,
    deleteError: null,
    saveError: null,
  },
);

const emit = defineEmits<{
  closeCreateTransaction: [];
  closeDeleteTransaction: [];
  closeEditTransaction: [];
  confirmDeleteTransaction: [];
  createFormStateChange: [state: FormState];
  editFormStateChange: [state: FormState];
  editSubmit: [payload: TransactionWritePayload];
  submit: [payload: TransactionWritePayload, options: TransactionSubmitOptions];
}>();
</script>

<template>
  <AccountTransactionFormModal
    :open="props.isCreateTransactionModalOpen"
    :account-id="props.accountId"
    :account-users="props.accountUsers"
    :account-balance="props.accountBalance"
    :actions="props.createTransactionActions"
    enable-create-and-add-another
    :financial-goals="props.financialGoals"
    form-id="transaction-form"
    :initial-values="props.createInitialValues"
    :is-loading-financial-goals="props.isLoadingFinancialGoals"
    :server-error="props.saveError"
    title="Nueva transacción"
    @close="emit('closeCreateTransaction')"
    @state-change="emit('createFormStateChange', $event)"
    @submit-with-options="(payload, options) => emit('submit', payload, options)"
  />

  <AccountTransactionFormModal
    :open="props.isEditTransactionModalOpen"
    :account-id="props.accountId"
    :account-users="props.accountUsers"
    :account-balance="props.accountBalance"
    :actions="props.editTransactionActions"
    :financial-goals="props.financialGoals"
    form-id="edit-transaction-form"
    :initial-values="props.selectedTransaction"
    :is-loading-financial-goals="props.isLoadingFinancialGoals"
    requires-initial-values
    :server-error="props.saveError"
    title="Editar transacción"
    @close="emit('closeEditTransaction')"
    @state-change="emit('editFormStateChange', $event)"
    @submit="emit('editSubmit', $event)"
  />

  <AccountTransactionDeleteModal
    :open="props.isDeleteTransactionModalOpen"
    :actions="props.deleteTransactionActions"
    :delete-error="props.deleteError"
    :transaction="props.selectedTransaction"
    @close="emit('closeDeleteTransaction')"
    @confirm="emit('confirmDeleteTransaction')"
  />
</template>
