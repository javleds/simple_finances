import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { computed } from 'vue';

import { createDefaultLoginFormValues, loginFormSchema } from '../schemas/authSchemas';
import type { LoginFormValues } from '../schemas/authSchemas';

export function useLoginForm() {
  const {
    errors,
    handleSubmit,
    isSubmitting,
    meta,
    resetForm,
    setFieldValue,
    validateField,
    values,
  } = useForm<LoginFormValues>({
    validationSchema: toTypedSchema(loginFormSchema),
    initialValues: createDefaultLoginFormValues(),
    validateOnMount: false,
  });

  const email = computed({
    get: () => values.email,
    set: (value: string) => setFieldValue('email', value, false),
  });

  const password = computed({
    get: () => values.password,
    set: (value: string) => setFieldValue('password', value, false),
  });

  const isSubmitDisabled = computed(() => isSubmitting.value || !meta.value.valid);

  const submitForm = handleSubmit((submittedValues) => submittedValues);

  async function handleEmailBlur(): Promise<void> {
    await validateField('email');
  }

  async function handlePasswordBlur(): Promise<void> {
    await validateField('password');
  }

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
    handleEmailBlur,
    handlePasswordBlur,
    submitForm,
    reset,
  };
}
