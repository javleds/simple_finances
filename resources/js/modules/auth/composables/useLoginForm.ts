import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { computed } from 'vue';

import { createDefaultLoginFormValues, loginFormSchema } from '../schemas/authSchemas';
import type { LoginFormValues } from '../schemas/authSchemas';

export function useLoginForm() {
  const { errors, handleSubmit, isSubmitting, meta, resetForm, setFieldValue, values } =
    useForm<LoginFormValues>({
      validationSchema: toTypedSchema(loginFormSchema),
      initialValues: createDefaultLoginFormValues(),
      validateOnMount: true,
    });

  const email = computed({
    get: () => values.email,
    set: (value: string) => setFieldValue('email', value, true),
  });

  const password = computed({
    get: () => values.password,
    set: (value: string) => setFieldValue('password', value, true),
  });

  const isSubmitDisabled = computed(() => isSubmitting.value || !meta.value.valid);

  const submitForm = handleSubmit((submittedValues) => submittedValues);

  function reset(): void {
    resetForm({
      values: createDefaultLoginFormValues(),
    });
  }

  return {
    email,
    password,
    errors,
    meta,
    isSubmitting,
    isSubmitDisabled,
    submitForm,
    reset,
  };
}
