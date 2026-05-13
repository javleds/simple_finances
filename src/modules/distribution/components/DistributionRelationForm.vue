<script setup lang="ts">
import { watch } from 'vue';

import { useDistributionRelationForm } from '@/modules/distribution/composables/useDistributionRelationForm';
import type {
  DistributionRelation,
  DistributionRelationWritePayload,
} from '@/modules/distribution/types';
import { AppInput, AppText, AppToggleButton } from '@/modules/shared/components';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const props = withDefaults(
  defineProps<{
    formId?: string;
    fixedIncomeId: string;
    initialValues?: Partial<DistributionRelation> | null;
    serverError?: string | null;
  }>(),
  {
    formId: 'distribution-relation-form',
    initialValues: null,
    serverError: null,
  },
);

const emit = defineEmits<{
  submit: [payload: DistributionRelationWritePayload];
  stateChange: [payload: FormState];
}>();

const typeOptions = [
  { value: 'transfer', label: 'Transferencia' },
  { value: 'savings', label: 'Ahorro' },
] as const;

const { name, amount, type, isSubmitting, isSubmitDisabled, meta, submitForm } =
  useDistributionRelationForm({
    fixedIncomeId: props.fixedIncomeId,
    initialValues: () => props.initialValues,
  });
const { error: nameError, touch: touchName } = useFormFieldInteraction('name');
const { error: amountError, touch: touchAmount } = useFormFieldInteraction('amount');

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
    <section
      v-if="props.serverError"
      class="rounded-xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText size="sm" class="text-(--app-color-danger)!">{{ props.serverError }}</AppText>
    </section>

    <AppInput
      id="distribution-relation-name"
      v-model="name"
      label="Concepto"
      placeholder="Ej. Ahorro operativo"
      :error="nameError"
      @blur="touchName"
      required
    />

    <AppInput
      id="distribution-relation-amount"
      v-model="amount"
      label="Cantidad"
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

    <div class="space-y-2">
      <label for="distribution-relation-type" class="text-sm font-medium text-(--app-color-label)">
        Tipo
      </label>
      <AppToggleButton
        id="distribution-relation-type"
        :model-value="type"
        :options="typeOptions"
        @update:model-value="type = $event as 'savings' | 'transfer'"
      />
    </div>
  </form>
</template>
