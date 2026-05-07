import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { computed } from 'vue';

import { createDefaultRegisterFormValues, registerFormSchema } from '../schemas/authSchemas';
import type { RegisterFormValues } from '../schemas/authSchemas';

export function useRegisterForm() {
  const { errors, handleSubmit, isSubmitting, meta, resetForm, setFieldValue, values } =
    useForm<RegisterFormValues>({
      validationSchema: toTypedSchema(registerFormSchema),
      initialValues: createDefaultRegisterFormValues(),
      validateOnMount: true,
    });

  const name = computed({
    get: () => values.name,
    set: (value: string) => setFieldValue('name', value, true),
  });

  const email = computed({
    get: () => values.email,
    set: (value: string) => setFieldValue('email', value, true),
  });

  const phoneNumber = computed({
    get: () => values.phoneNumber,
    set: (value: string) => setFieldValue('phoneNumber', value, true),
  });

  const password = computed({
    get: () => values.password,
    set: (value: string) => setFieldValue('password', value, true),
  });

  const passwordConfirmation = computed({
    get: () => values.passwordConfirmation,
    set: (value: string) => setFieldValue('passwordConfirmation', value, true),
  });

  const termsAccepted = computed({
    get: () => values.termsAccepted,
    set: (value: boolean) => setFieldValue('termsAccepted', value, true),
  });

  const privacyPolicyAccepted = computed({
    get: () => values.privacyPolicyAccepted,
    set: (value: boolean) => setFieldValue('privacyPolicyAccepted', value, true),
  });

  const isSubmitDisabled = computed(() => isSubmitting.value || !meta.value.valid);

  const submitForm = handleSubmit((submittedValues) => submittedValues);

  function reset(): void {
    resetForm({
      values: createDefaultRegisterFormValues(),
    });
  }

  return {
    name,
    email,
    phoneNumber,
    password,
    passwordConfirmation,
    termsAccepted,
    privacyPolicyAccepted,
    errors,
    meta,
    isSubmitting,
    isSubmitDisabled,
    submitForm,
    reset,
  };
}
