<script setup lang="ts">
import type { AppModalAction } from '@/modules/shared/types/modal';
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

const props = withDefaults(
  defineProps<{
    actions: ReadonlyArray<AppModalAction>;
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
