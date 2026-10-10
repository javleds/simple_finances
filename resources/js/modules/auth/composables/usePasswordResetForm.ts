import { usePrimeForm } from '@/modules/shared/composables/usePrimeForm';
import { computed } from 'vue';

import {
    createDefaultPasswordResetFormValues,
    passwordResetFormSchema,
} from '../schemas/authSchemas';
import type { PasswordResetFormValues } from '../schemas/authSchemas';

type UsePasswordResetFormOptions = {
    token?: string;
    email?: string;
};

export function usePasswordResetForm(options: UsePasswordResetFormOptions = {}) {
    const { errors, handleSubmit, isSubmitting, meta, setFieldValue, values } =
        usePrimeForm<PasswordResetFormValues>({
            schema: passwordResetFormSchema,
            initialValues: createDefaultPasswordResetFormValues(options.token, options.email),
        });

    const token = computed({
        get: () => values.token,
        set: (value: string) => setFieldValue('token', value, true),
    });

    const email = computed({
        get: () => values.email,
        set: (value: string) => setFieldValue('email', value, true),
    });

    const password = computed({
        get: () => values.password,
        set: (value: string) => setFieldValue('password', value, true),
    });

    const passwordConfirmation = computed({
        get: () => values.passwordConfirmation,
        set: (value: string) => setFieldValue('passwordConfirmation', value, true),
    });

    const isSubmitDisabled = computed(() => isSubmitting.value || !meta.value.valid);

    const submitForm = handleSubmit((submittedValues) => submittedValues);

    return {
        token,
        email,
        password,
        passwordConfirmation,
        errors,
        meta,
        isSubmitting,
        isSubmitDisabled,
        submitForm,
    };
}
