<script setup lang="ts">
import { watch } from 'vue';

import { useDistributionRuleForm } from '@/modules/distribution/composables/useDistributionRuleForm';
import type { DistributionRule, DistributionRuleWritePayload } from '@/modules/distribution/types';
import { AppInput, AppText, AppToggleButton } from '@/modules/shared/components';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const props = withDefaults(
  defineProps<{
    formId?: string;
    initialValues?: Partial<DistributionRule> | null;
    serverError?: string | null;
  }>(),
  {
    formId: 'distribution-rule-form',
    initialValues: null,
    serverError: null,
  },
);

const emit = defineEmits<{
  submit: [payload: DistributionRuleWritePayload];
  stateChange: [payload: FormState];
}>();

const frequencyOptions = [
  { value: 'monthly', label: 'Mensual' },
  { value: 'semi_monthly', label: 'Quincenal' },
] as const;

const { name, frequency, isSubmitting, isSubmitDisabled, meta, submitForm } =
  useDistributionRuleForm({
    initialValues: () => props.initialValues,
  });
const { error: nameError, touch: touchName } = useFormFieldInteraction('name');

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
      id="distribution-rule-name"
      v-model="name"
      label="Nombre"
      placeholder="Ej. Ingreso fijo principal"
      :error="nameError"
      @blur="touchName"
      required
    />

    <div class="space-y-2">
      <label for="distribution-rule-frequency" class="text-sm font-medium text-(--app-color-label)">
        Frecuencia
      </label>
      <AppToggleButton
        id="distribution-rule-frequency"
        :model-value="frequency"
        :options="frequencyOptions"
        @update:model-value="frequency = $event as 'monthly' | 'semi_monthly'"
      />
    </div>
  </form>
</template>
