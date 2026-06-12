<script setup lang="ts">
import type { Component } from 'vue';

import DistributionRuleForm from '@/modules/distribution/components/DistributionRuleForm.vue';
import type { DistributionRule, DistributionRuleWritePayload } from '@/modules/distribution/types';
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
    actions: ReadonlyArray<ModalAction>;
    formId: string;
    open: boolean;
    requiresInitialValues?: boolean;
    serverError?: string | null;
    title: string;
    initialValues?: DistributionRule | null;
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
  submit: [payload: DistributionRuleWritePayload];
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
    <DistributionRuleForm
      v-if="!props.requiresInitialValues || props.initialValues"
      :form-id="props.formId"
      :initial-values="props.initialValues ?? undefined"
      :server-error="props.serverError"
      @state-change="emit('stateChange', $event)"
      @submit="emit('submit', $event)"
    />
  </AppModal>
</template>
