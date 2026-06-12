import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { computed, toValue, watch } from 'vue';

import {
  accountInviteFormSchema,
  createDefaultAccountInviteFormValues,
  mapAccountInviteFormToWritePayload,
} from '../schemas/accountInviteSchemas';
import type {
  AccountInvite,
  AccountInviteFormValues,
  AccountInviteWritePayload,
} from '../schemas/accountInviteSchemas';

type UseAccountInviteFormOptions = {
  accountId: string;
  initialValues?: Partial<AccountInvite> | null | (() => Partial<AccountInvite> | null | undefined);
};

export function useAccountInviteForm(options: UseAccountInviteFormOptions) {
  const resolvedInitialValues = computed(() =>
    createDefaultAccountInviteFormValues(toValue(options.initialValues)),
  );

  const { errors, handleSubmit, isSubmitting, meta, resetForm, setFieldValue, values } =
    useForm<AccountInviteFormValues>({
      validationSchema: toTypedSchema(accountInviteFormSchema),
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

  const email = computed({
    get: () => values.email,
    set: (value: string) => setFieldValue('email', value, true),
  });

  const percentage = computed({
    get: () => values.percentage,
    set: (value: string) => setFieldValue('percentage', value, true),
  });

  const isSubmitDisabled = computed(() => isSubmitting.value || !meta.value.valid);

  const submitForm = handleSubmit(
    (submittedValues): AccountInviteWritePayload =>
      mapAccountInviteFormToWritePayload(options.accountId, submittedValues),
  );

  return {
    email,
    percentage,
    errors,
    meta,
    isSubmitting,
    isSubmitDisabled,
    submitForm,
  };
}
