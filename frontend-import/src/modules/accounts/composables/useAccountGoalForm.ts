import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { computed, toValue, watch } from 'vue';

import {
  accountGoalFormSchema,
  createDefaultAccountGoalFormValues,
  mapAccountGoalFormToWritePayload,
} from '../schemas/accountGoalSchemas';
import type {
  AccountGoal,
  AccountGoalFormValues,
  AccountGoalWritePayload,
} from '../schemas/accountGoalSchemas';

type UseAccountGoalFormOptions = {
  accountId: string;
  initialValues?: Partial<AccountGoal> | null | (() => Partial<AccountGoal> | null | undefined);
};

export function useAccountGoalForm(options: UseAccountGoalFormOptions) {
  const resolvedInitialValues = computed(() =>
    createDefaultAccountGoalFormValues(toValue(options.initialValues)),
  );

  const { errors, handleSubmit, isSubmitting, meta, resetForm, setFieldValue, values } =
    useForm<AccountGoalFormValues>({
      validationSchema: toTypedSchema(accountGoalFormSchema),
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

  const amount = computed({
    get: () => values.amount,
    set: (value: string) => setFieldValue('amount', value, true),
  });

  const deadline = computed({
    get: () => values.deadline,
    set: (value: string) => setFieldValue('deadline', value, true),
  });

  const isSubmitDisabled = computed(() => isSubmitting.value || !meta.value.valid);

  const submitForm = handleSubmit(
    (submittedValues): AccountGoalWritePayload =>
      mapAccountGoalFormToWritePayload(options.accountId, submittedValues),
  );

  return {
    name,
    amount,
    deadline,
    errors,
    meta,
    isSubmitting,
    isSubmitDisabled,
    submitForm,
  };
}
