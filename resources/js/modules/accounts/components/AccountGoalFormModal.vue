<script setup lang="ts">
import type { AppModalAction } from '@/modules/shared/types/modal';
import AccountGoalForm from '@/modules/accounts/components/AccountGoalForm.vue';
import type {
    AccountGoal,
    AccountGoalWritePayload,
} from '@/modules/accounts/schemas/accountGoalSchemas';
import { AppModal } from '@/modules/shared/components';

type FormState = {
    canSubmit: boolean;
    isSubmitting: boolean;
};

const props = withDefaults(
    defineProps<{
        accountId: string;
        actions: ReadonlyArray<AppModalAction>;
        formId: string;
        open: boolean;
        serverError?: string | null;
        title: string;
        initialValues?: Partial<AccountGoal> | null;
    }>(),
    {
        initialValues: null,
        serverError: null,
    },
);

const emit = defineEmits<{
    close: [];
    stateChange: [state: FormState];
    submit: [payload: AccountGoalWritePayload];
}>();
</script>

<template>
    <AppModal
        presentation="sheet"
        :open="props.open"
        :actions="props.actions"
        :title="props.title"
        variant="default"
        @close="emit('close')"
    >
        <AccountGoalForm
            v-if="props.accountId"
            :account-id="props.accountId"
            :form-id="props.formId"
            :initial-values="props.initialValues"
            :server-error="props.serverError"
            @state-change="emit('stateChange', $event)"
            @submit="emit('submit', $event)"
        />
    </AppModal>
</template>
