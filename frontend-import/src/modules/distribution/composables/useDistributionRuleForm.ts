import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { computed, toValue, watch } from 'vue';

import {
  createDefaultDistributionRuleFormValues,
  distributionRuleFormSchema,
  mapDistributionRuleFormToWritePayload,
} from '../schemas/distributionSchemas';
import type {
  DistributionRule,
  DistributionRuleFormValues,
  DistributionRuleWritePayload,
} from '../types';

type UseDistributionRuleFormOptions = {
  initialValues?:
    | Partial<DistributionRule>
    | null
    | (() => Partial<DistributionRule> | null | undefined);
};

export function useDistributionRuleForm(options: UseDistributionRuleFormOptions = {}) {
  const resolvedInitialValues = computed(() =>
    createDefaultDistributionRuleFormValues(toValue(options.initialValues)),
  );

  const { errors, handleSubmit, isSubmitting, meta, resetForm, setFieldValue, values } =
    useForm<DistributionRuleFormValues>({
      validationSchema: toTypedSchema(distributionRuleFormSchema),
      initialValues: resolvedInitialValues.value,
      validateOnMount: true,
    });

  watch(
    resolvedInitialValues,
    (nextValues) => {
      resetForm({
        values: nextValues,
      });
    },
    { deep: true },
  );

  const name = computed({
    get: () => values.name,
    set: (value: string) => setFieldValue('name', value, true),
  });

  const frequency = computed({
    get: () => values.frequency,
    set: (value: DistributionRuleFormValues['frequency']) =>
      setFieldValue('frequency', value, true),
  });

  const isSubmitDisabled = computed(() => isSubmitting.value || !meta.value.valid);

  const submitForm = handleSubmit(
    (submittedValues): DistributionRuleWritePayload =>
      mapDistributionRuleFormToWritePayload(submittedValues),
  );

  return {
    name,
    frequency,
    errors,
    meta,
    isSubmitting,
    isSubmitDisabled,
    submitForm,
  };
}
