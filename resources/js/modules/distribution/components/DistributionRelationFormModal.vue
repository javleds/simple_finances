<script setup lang="ts">
import type { AppModalAction } from '@/modules/shared/types/modal';
import DistributionRelationForm from '@/modules/distribution/components/DistributionRelationForm.vue';
import type {
  DistributionRelation,
  DistributionRelationWritePayload,
} from '@/modules/distribution/types';
import { AppModal } from '@/modules/shared/components';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const props = withDefaults(
  defineProps<{
    actions: ReadonlyArray<AppModalAction>;
    fixedIncomeId: string;
    formId: string;
    open: boolean;
    requiresInitialValues?: boolean;
    serverError?: string | null;
    title: string;
    initialValues?: DistributionRelation | null;
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
  submit: [payload: DistributionRelationWritePayload];
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
    <DistributionRelationForm
      v-if="!props.requiresInitialValues || props.initialValues"
      :fixed-income-id="props.fixedIncomeId"
      :form-id="props.formId"
      :initial-values="props.initialValues ?? undefined"
      :server-error="props.serverError"
      @state-change="emit('stateChange', $event)"
      @submit="emit('submit', $event)"
    />
  </AppModal>
</template>
