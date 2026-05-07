import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { computed } from 'vue';

import {
  createDefaultPasswordRecoveryFormValues,
  passwordRecoveryFormSchema,
} from '../schemas/authSchemas';
import type { PasswordRecoveryFormValues } from '../schemas/authSchemas';

export function usePasswordRecoveryForm() {
  const { errors, handleSubmit, isSubmitting, meta, setFieldValue, values } =
    useForm<PasswordRecoveryFormValues>({
      validationSchema: toTypedSchema(passwordRecoveryFormSchema),
      initialValues: createDefaultPasswordRecoveryFormValues(),
      validateOnMount: true,
    });

  const email = computed({
    get: () => values.email,
    set: (value: string) => setFieldValue('email', value, true),
  });

  const isSubmitDisabled = computed(() => isSubmitting.value || !meta.value.valid);

  const submitForm = handleSubmit((submittedValues) => submittedValues);

  return {
    email,
    errors,
    meta,
    isSubmitting,
    isSubmitDisabled,
    submitForm,
  };
}
