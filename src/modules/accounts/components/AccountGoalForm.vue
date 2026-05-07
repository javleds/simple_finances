<script setup lang="ts">
import { watch } from 'vue';

import { useAccountGoalForm } from '@/modules/accounts/composables/useAccountGoalForm';
import type { AccountGoal, AccountGoalWritePayload } from '@/modules/accounts/schemas/accountGoalSchemas';
import { AppDatePicker, AppInput, AppText } from '@/modules/shared/components';

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

const { name, amount, deadline, errors, isSubmitting, isSubmitDisabled, meta, submitForm } =
  useAccountGoalForm({
    accountId: props.accountId,
    initialValues: () => props.initialValues,
  });

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
    <section v-if="props.serverError" class="rounded-xl border border-(--app-color-danger) px-4 py-3">
      <AppText size="sm" class="text-(--app-color-danger)!">
        {{ props.serverError }}
      </AppText>
    </section>

    <AppInput
      id="goal-name"
      v-model="name"
      label="Nombre"
      placeholder="Ej. Fondo operativo mensual"
      :error="errors.name"
      required
    />

    <AppInput
      id="goal-target-amount"
      v-model="amount"
      label="Cantidad objetivo"
      type="number"
      inputmode="decimal"
      min="0"
      step="0.01"
      placeholder="0.00"
      :error="errors.amount"
      required
    />

    <AppDatePicker
      id="goal-deadline"
      v-model="deadline"
      label="Fecha límite"
      placeholder="AAAA-MM-DD"
      :error="errors.deadline"
      required
    />
  </form>
</template>
