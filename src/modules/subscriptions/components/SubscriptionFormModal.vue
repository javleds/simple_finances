<script setup lang="ts">
import type { Component } from 'vue';

import SubscriptionsForm from '@/modules/subscriptions/components/SubscriptionsForm.vue';
import type { Subscription, SubscriptionWritePayload } from '@/modules/subscriptions/types';
import { AppModal } from '@/modules/shared/components';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

type SelectOption = {
  value: string;
  label: string;
  description?: string;
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
    fundingAccountOptions: SelectOption[];
    open: boolean;
    requiresInitialValues?: boolean;
    serverError?: string | null;
    title: string;
    initialValues?: Subscription | null;
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
  submit: [payload: SubscriptionWritePayload];
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
    <SubscriptionsForm
      v-if="!props.requiresInitialValues || props.initialValues"
      :form-id="props.formId"
      :funding-account-options="props.fundingAccountOptions"
      :initial-values="props.initialValues ?? undefined"
      :server-error="props.serverError"
      @state-change="emit('stateChange', $event)"
      @submit="emit('submit', $event)"
    />
  </AppModal>
</template>
