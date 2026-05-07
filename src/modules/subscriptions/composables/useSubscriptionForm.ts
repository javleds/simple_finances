import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { computed, toValue, watch } from 'vue';

import {
  createDefaultSubscriptionFormValues,
  mapSubscriptionFormToWritePayload,
  subscriptionFormSchema,
} from '../schemas/subscriptionSchemas';
import type { Subscription, SubscriptionFormValues, SubscriptionWritePayload } from '../types';

type UseSubscriptionFormOptions = {
  initialValues?: Partial<Subscription> | null | (() => Partial<Subscription> | null | undefined);
};

export function useSubscriptionForm(options: UseSubscriptionFormOptions = {}) {
  const resolvedInitialValues = computed(() =>
    createDefaultSubscriptionFormValues(toValue(options.initialValues)),
  );

  const {
    errors,
    handleSubmit,
    isSubmitting,
    meta,
    resetForm,
    setFieldValue,
    values,
  } = useForm<SubscriptionFormValues>({
    validationSchema: toTypedSchema(subscriptionFormSchema),
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

  const startDate = computed({
    get: () => values.startDate,
    set: (value: string) => setFieldValue('startDate', value, true),
  });

  const frequencyEvery = computed({
    get: () => values.frequencyUnit,
    set: (value: string) => setFieldValue('frequencyUnit', value, true),
  });

  const frequencyUnit = computed({
    get: () => values.frequencyType,
    set: (value: SubscriptionFormValues['frequencyType']) =>
      setFieldValue('frequencyType', value, true),
  });

  const cancellationDate = computed({
    get: () => values.finishedAt,
    set: (value: string) => setFieldValue('finishedAt', value, true),
  });

  const fundingAccountId = computed({
    get: () => values.fundingAccountId,
    set: (value: string | null) => setFieldValue('fundingAccountId', value, true),
  });

  const isSubmitDisabled = computed(() => isSubmitting.value || !meta.value.valid);

  const submitForm = handleSubmit((submittedValues): SubscriptionWritePayload =>
    mapSubscriptionFormToWritePayload(submittedValues),
  );

  return {
    name,
    amount,
    startDate,
    frequencyEvery,
    frequencyUnit,
    cancellationDate,
    fundingAccountId,
    errors,
    meta,
    isSubmitting,
    isSubmitDisabled,
    submitForm,
  };
}
