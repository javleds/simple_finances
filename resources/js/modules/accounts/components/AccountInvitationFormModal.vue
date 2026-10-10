<script setup lang="ts">
import type { AppModalAction } from '@/modules/shared/types/modal';
import AccountInvitationForm from '@/modules/accounts/components/AccountInvitationForm.vue';
import type {
    AccountInvite,
    AccountInviteWritePayload,
} from '@/modules/accounts/schemas/accountInviteSchemas';
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
        initialValues?: Partial<AccountInvite> | null;
    }>(),
    {
        initialValues: null,
        serverError: null,
    },
);

const emit = defineEmits<{
    close: [];
    stateChange: [state: FormState];
    submit: [payload: AccountInviteWritePayload];
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
        <AccountInvitationForm
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
