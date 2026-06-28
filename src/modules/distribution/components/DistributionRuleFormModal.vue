<script setup lang="ts">
import type { AppModalAction } from '@/modules/shared/types/modal';
import DistributionRuleForm from '@/modules/distribution/components/DistributionRuleForm.vue';
import type { DistributionRule, DistributionRuleWritePayload } from '@/modules/distribution/types';
import { AppModal } from '@/modules/shared/components';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const props = withDefaults(
  defineProps<{
    actions: ReadonlyArray<AppModalAction>;
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
