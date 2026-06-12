<script setup lang="ts">
import type { Component } from 'vue';

import AccountCompletePendingByUserModal from '@/modules/accounts/components/AccountCompletePendingByUserModal.vue';
import AccountTransactionCompleteModal from '@/modules/accounts/components/AccountTransactionCompleteModal.vue';
import AccountTransactionDeleteModal from '@/modules/accounts/components/AccountTransactionDeleteModal.vue';
import AccountTransactionFormModal from '@/modules/accounts/components/AccountTransactionFormModal.vue';
import type { AccountMember, AccountPendingByUser } from '@/modules/accounts/types';
import type { AccountGoal } from '@/modules/accounts/schemas/accountGoalSchemas';
import type { Transaction, TransactionWritePayload } from '@/modules/transactions/types';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

type ModalAction = {
  key: string;
  label: string;
  tone?: 'primary' | 'danger' | 'neutral';
  icon?: Component;
  type?: 'button' | 'submit' | 'reset';
  disabled?: boolean;
  form?: string;
  autoClose?: boolean;
};

const props = withDefaults(
  defineProps<{
    accountId: string;
    accountUsers: AccountMember[];
    completePendingByUserActions: ReadonlyArray<ModalAction>;
    completePendingByUserError?: string | null;
    completeTransactionActions: ReadonlyArray<ModalAction>;
    createTransactionActions: ReadonlyArray<ModalAction>;
    deleteError?: string | null;
    deleteTransactionActions: ReadonlyArray<ModalAction>;
    editTransactionActions: ReadonlyArray<ModalAction>;
    financialGoals: AccountGoal[];
    isCompletePendingByUserModalOpen: boolean;
    isCompleteTransactionModalOpen: boolean;
    isCreateTransactionModalOpen: boolean;
    isDeleteTransactionModalOpen: boolean;
    isEditTransactionModalOpen: boolean;
    isLoadingFinancialGoals: boolean;
    saveError?: string | null;
    selectedPendingByUser: AccountPendingByUser | null;
    selectedTransaction: Transaction | null;
  }>(),
  {
    completePendingByUserError: null,
    deleteError: null,
    saveError: null,
  },
);

const emit = defineEmits<{
  closeCompletePendingByUser: [];
  closeCompleteTransaction: [];
  closeCreateTransaction: [];
  closeDeleteTransaction: [];
  closeEditTransaction: [];
  confirmCompletePendingByUser: [];
  confirmCompleteTransaction: [];
  confirmDeleteTransaction: [];
  createFormStateChange: [state: FormState];
  editFormStateChange: [state: FormState];
  editSubmit: [payload: TransactionWritePayload];
  submit: [payload: TransactionWritePayload];
}>();
</script>

<template>
  <AccountTransactionFormModal
    :open="props.isCreateTransactionModalOpen"
    :account-id="props.accountId"
    :account-users="props.accountUsers"
    :actions="props.createTransactionActions"
    :financial-goals="props.financialGoals"
    form-id="transaction-form"
    :is-loading-financial-goals="props.isLoadingFinancialGoals"
    :server-error="props.saveError"
    title="Nueva transacción"
    @close="emit('closeCreateTransaction')"
    @state-change="emit('createFormStateChange', $event)"
    @submit="emit('submit', $event)"
  />

  <AccountTransactionFormModal
    :open="props.isEditTransactionModalOpen"
    :account-id="props.accountId"
    :account-users="props.accountUsers"
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

  <AccountTransactionCompleteModal
    :open="props.isCompleteTransactionModalOpen"
    :actions="props.completeTransactionActions"
    :save-error="props.saveError"
    :transaction="props.selectedTransaction"
    @close="emit('closeCompleteTransaction')"
    @confirm="emit('confirmCompleteTransaction')"
  />

  <AccountCompletePendingByUserModal
    :open="props.isCompletePendingByUserModalOpen"
    :actions="props.completePendingByUserActions"
    :complete-error="props.completePendingByUserError"
    :pending-user="props.selectedPendingByUser"
    @close="emit('closeCompletePendingByUser')"
    @confirm="emit('confirmCompletePendingByUser')"
  />
</template>
