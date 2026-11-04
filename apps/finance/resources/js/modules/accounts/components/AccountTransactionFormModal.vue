<script setup lang="ts">
import { ref, watch } from 'vue';
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
    savedTransactionId?: string | null;
};

const props = withDefaults(
    defineProps<{
        accountId: string;
        accountUsers: AccountMember[];
        accountBalance?: number | null;
        currentUserId?: string | null;
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
        currentUserId: null,
        initialValues: null,
        requiresInitialValues: false,
        serverError: null,
    },
);

const savedTransactionId = ref<string | null>(null);
watch(
    () => props.open,
    () => {
        savedTransactionId.value = null;
    },
);
function updateState(state: FormState): void {
    savedTransactionId.value = state.savedTransactionId ?? null;
    emit('stateChange', state);
}
const transactionForm = ref<InstanceType<typeof TransactionsForm> | null>(null);
function requestClose(): void {
    if (transactionForm.value?.confirmClose() !== false) emit('close');
}

const emit = defineEmits<{
    close: [];
    stateChange: [state: FormState];
    submit: [payload: TransactionWritePayload];
    submitWithOptions: [payload: TransactionWritePayload, options: TransactionSubmitOptions];
}>();
</script>

<template>
    <AppModal
        presentation="fullscreen"
        :open="props.open"
        :actions="props.actions"
        :title="savedTransactionId ? 'Movimiento guardado · Comprobantes' : props.title"
        variant="default"
        @close="requestClose"
    >
        <TransactionsForm
            ref="transactionForm"
            v-if="props.open && (!props.requiresInitialValues || props.initialValues)"
            :form-id="props.formId"
            :saving="props.actions.some((action) => action.loading)"
            :account-users="props.accountUsers"
            :account-balance="props.accountBalance"
            :current-user-id="props.currentUserId"
            :enable-create-and-add-another="props.enableCreateAndAddAnother"
            :financial-goals="props.financialGoals"
            :is-loading-financial-goals="props.isLoadingFinancialGoals"
            :locked-account-id="props.accountId"
            :initial-values="props.initialValues ?? undefined"
            :server-error="props.serverError"
            @state-change="updateState"
            @submit="emit('submit', $event)"
            @submit-with-options="(payload, options) => emit('submitWithOptions', payload, options)"
        />
    </AppModal>
</template>
