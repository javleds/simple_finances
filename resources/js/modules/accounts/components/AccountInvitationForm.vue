<script setup lang="ts">
import Message from 'primevue/message';
import { watch } from 'vue';

import { useAccountInviteForm } from '@/modules/accounts/composables/useAccountInviteForm';
import type {
    AccountInvite,
    AccountInviteWritePayload,
} from '@/modules/accounts/schemas/accountInviteSchemas';
import { AppInput } from '@/modules/shared/components';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';

type FormState = {
    canSubmit: boolean;
    isSubmitting: boolean;
};

const props = withDefaults(
    defineProps<{
        formId?: string;
        accountId: string;
        initialValues?: Partial<AccountInvite> | null;
        serverError?: string | null;
    }>(),
    {
        formId: 'account-invitation-form',
        initialValues: null,
        serverError: null,
    },
);

const emit = defineEmits<{
    submit: [payload: AccountInviteWritePayload];
    stateChange: [payload: FormState];
}>();

const { email, percentage, isSubmitting, isSubmitDisabled, meta, submitForm } =
    useAccountInviteForm({
        accountId: props.accountId,
        initialValues: () => props.initialValues,
    });
const { error: emailError, touch: touchEmail } = useFormFieldInteraction('email');
const { error: percentageError, touch: touchPercentage } = useFormFieldInteraction('percentage');

watch(
    [isSubmitDisabled, isSubmitting, meta],
    () => {
        emit('stateChange', {
            canSubmit: !isSubmitDisabled.value,
            isSubmitting: isSubmitting.value,
        });
    },
    { immediate: true, deep: true },
);

async function handleSubmit(): Promise<void> {
    const payload = await submitForm();

    if (!payload) {
        return;
    }

    emit('submit', payload);
}
</script>

<template>
    <form :id="props.formId" class="space-y-6" @submit.prevent="handleSubmit">
        <Message v-if="props.serverError" severity="error">{{ props.serverError }}</Message>

        <AppInput
            id="invite-email"
            v-model="email"
            label="Correo electrónico invitado"
            type="email"
            placeholder="colaborador@empresa.com"
            :error="emailError"
            @blur="touchEmail"
            required
        />

        <AppInput
            id="invite-percentage"
            v-model="percentage"
            label="Porcentaje asignado (opcional)"
            type="number"
            inputmode="decimal"
            min="0"
            max="100"
            step="0.01"
            placeholder="0.00"
            :error="percentageError"
            @blur="touchPercentage"
        />
    </form>
</template>
