<script setup lang="ts">
import type { Component } from 'vue';

import type { AccountMember } from '@/modules/accounts/types';
import type { AccountGoal } from '@/modules/accounts/schemas/accountGoalSchemas';
import TransactionsForm from '@/modules/transactions/components/TransactionsForm.vue';
import type { Transaction, TransactionWritePayload } from '@/modules/transactions/types';
import { AppModal } from '@/modules/shared/components';

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
    actions: ReadonlyArray<ModalAction>;
    financialGoals: AccountGoal[];
    formId: string;
    isLoadingFinancialGoals: boolean;
    open: boolean;
    requiresInitialValues?: boolean;
    serverError?: string | null;
    title: string;
    initialValues?: Transaction | null;
  }>(),
  {
    initialValues: null,
    requiresInitialValues: false,
    serverError: null,
  },
);

const emit = defineEmits<{
  close: [];
  stateChange: [state: FormState];
  submit: [payload: TransactionWritePayload];
}>();
</script>

<template>
  <AppModal
    :open="props.open"
    :actions="props.actions"
    :title="props.title"
    variant="default"
    @close="emit('close')"
  >
    <TransactionsForm
      v-if="!props.requiresInitialValues || props.initialValues"
      :form-id="props.formId"
      :account-users="props.accountUsers"
      :financial-goals="props.financialGoals"
      :is-loading-financial-goals="props.isLoadingFinancialGoals"
      :locked-account-id="props.accountId"
      :initial-values="props.initialValues ?? undefined"
      :server-error="props.serverError"
      @state-change="emit('stateChange', $event)"
      @submit="emit('submit', $event)"
    />
  </AppModal>
</template>
