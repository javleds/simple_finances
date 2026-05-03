import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { computed, toValue, watch } from 'vue';

import {
  accountFormSchema,
  createDefaultAccountFormValues,
  mapAccountFormToWritePayload,
} from '../schemas/accountSchemas';
import type { Account, AccountFormValues, AccountWritePayload } from '../types';

type UseAccountFormOptions = {
  initialValues?: Partial<Account> | null | (() => Partial<Account> | null | undefined);
};

export function useAccountForm(options: UseAccountFormOptions = {}) {
  const resolvedInitialValues = computed(() =>
    createDefaultAccountFormValues(toValue(options.initialValues)),
  );

  const {
    errors,
    handleSubmit,
    isSubmitting,
    meta,
    resetForm,
    setFieldValue,
    values,
  } = useForm<AccountFormValues>({
    validationSchema: toTypedSchema(accountFormSchema),
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

  const showCreditFields = computed(() => values.isCredit === 'yes');

  watch(showCreditFields, (isCredit) => {
    if (isCredit) {
      return;
    }

    setFieldValue('creditLine', '', false);
    setFieldValue('closingDay', '', false);
  });

  const name = computed({
    get: () => values.name,
    set: (value: string) => setFieldValue('name', value, true),
  });

  const color = computed({
    get: () => values.color,
    set: (value: string) => setFieldValue('color', value, true),
  });

  const description = computed({
    get: () => values.description,
    set: (value: string) => setFieldValue('description', value, true),
  });

  const isVirtual = computed({
    get: () => values.isVirtual,
    set: (value: AccountFormValues['isVirtual']) => setFieldValue('isVirtual', value, true),
  });

  const isCredit = computed({
    get: () => values.isCredit,
    set: (value: AccountFormValues['isCredit']) => setFieldValue('isCredit', value, true),
  });

  const creditLine = computed({
    get: () => values.creditLine,
    set: (value: string) => setFieldValue('creditLine', value, true),
  });

  const closingDay = computed({
    get: () => values.closingDay,
    set: (value: string) => setFieldValue('closingDay', value, true),
  });

  const isSubmitDisabled = computed(() => isSubmitting.value || !meta.value.valid);

  const submitForm = handleSubmit((submittedValues): AccountWritePayload =>
    mapAccountFormToWritePayload(submittedValues),
  );

  return {
    name,
    color,
    description,
    isVirtual,
    isCredit,
    creditLine,
    closingDay,
    errors,
    meta,
    isSubmitting,
    isSubmitDisabled,
    showCreditFields,
    resetForm,
    submitForm,
  };
}
