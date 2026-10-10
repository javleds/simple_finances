<script setup lang="ts">
import Message from 'primevue/message';
import { watch } from 'vue';

import { useAccountGoalForm } from '@/modules/accounts/composables/useAccountGoalForm';
import type {
    AccountGoal,
    AccountGoalWritePayload,
} from '@/modules/accounts/schemas/accountGoalSchemas';
import { AppDatePicker, AppInput } from '@/modules/shared/components';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';

type FormState = {
    canSubmit: boolean;
    isSubmitting: boolean;
};

const props = withDefaults(
    defineProps<{
        formId?: string;
        accountId: string;
        initialValues?: Partial<AccountGoal> | null;
        serverError?: string | null;
    }>(),
    {
        formId: 'account-goal-form',
        initialValues: null,
        serverError: null,
    },
);

const emit = defineEmits<{
    submit: [payload: AccountGoalWritePayload];
    stateChange: [payload: FormState];
}>();

const { name, amount, deadline, isSubmitting, isSubmitDisabled, meta, submitForm } =
    useAccountGoalForm({
        accountId: props.accountId,
        initialValues: () => props.initialValues,
    });
const { error: nameError, touch: touchName } = useFormFieldInteraction('name');
const { error: amountError, touch: touchAmount } = useFormFieldInteraction('amount');
const { error: deadlineError, touch: touchDeadline } = useFormFieldInteraction('deadline');

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
            id="goal-name"
            v-model="name"
            label="Nombre"
            placeholder="Ej. Fondo operativo mensual"
            :error="nameError"
            @blur="touchName"
            required
        />

        <AppInput
            id="goal-target-amount"
            v-model="amount"
            label="Cantidad objetivo"
            mask="amount"
            type="number"
            inputmode="decimal"
            min="0"
            step="0.01"
            placeholder="0.00"
            :error="amountError"
            @blur="touchAmount"
            required
        />

        <AppDatePicker
            id="goal-deadline"
            v-model="deadline"
            label="Fecha límite"
            placeholder="AAAA-MM-DD"
            :error="deadlineError"
            @change="touchDeadline"
            @blur="touchDeadline"
        />
    </form>
</template>
