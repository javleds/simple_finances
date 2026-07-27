<script setup lang="ts">
import type { AppModalAction } from '@/modules/shared/types/modal';
import type { AccountMember } from '@/modules/accounts/types';
import type { AccountGoal } from '@/modules/accounts/schemas/accountGoalSchemas';
import TransactionsForm from '@/modules/transactions/components/TransactionsForm.vue';
import type {
  Transaction,
  TransactionSubmitOptions,
  TransactionWritePayload,
} from '@/modules/transactions/types';
import { AppModal } from '@/modules/shared/components';

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
    actions: ReadonlyArray<AppModalAction>;
    financialGoals: AccountGoal[];
    formId: string;
    enableCreateAndAddAnother?: boolean;
    isLoadingFinancialGoals: boolean;
    open: boolean;
    requiresInitialValues?: boolean;
    serverError?: string | null;
    title: string;
    initialValues?: Partial<Transaction> | null;
  }>(),
  {
    enableCreateAndAddAnother: false,
    accountBalance: null,
    initialValues: null,
    requiresInitialValues: false,
    serverError: null,
  },
);

const emit = defineEmits<{
  close: [];
  stateChange: [state: FormState];
  submit: [payload: TransactionWritePayload];
  submitWithOptions: [payload: TransactionWritePayload, options: TransactionSubmitOptions];
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
      :account-balance="props.accountBalance"
      :enable-create-and-add-another="props.enableCreateAndAddAnother"
      :financial-goals="props.financialGoals"
      :is-loading-financial-goals="props.isLoadingFinancialGoals"
      :locked-account-id="props.accountId"
      :initial-values="props.initialValues ?? undefined"
      :server-error="props.serverError"
      @state-change="emit('stateChange', $event)"
      @submit="emit('submit', $event)"
      @submit-with-options="
        (payload, options) => emit('submitWithOptions', payload, options)
      "
    />
  </AppModal>
</template>
