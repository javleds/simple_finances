import { usePrimeForm } from '@/modules/shared/composables/usePrimeForm';
import { computed, toValue, watch } from 'vue';

import {
    createDefaultProfileFormValues,
    mapProfileFormToWritePayload,
    profileFormSchema,
} from '../schemas/profileSchemas';
import type { Profile, ProfileFormValues, ProfileWritePayload } from '../schemas/profileSchemas';

type UseProfileFormOptions = {
    initialValues?: Partial<Profile> | null | (() => Partial<Profile> | null | undefined);
};

export function useProfileForm(options: UseProfileFormOptions = {}) {
    const resolvedInitialValues = computed(() =>
        createDefaultProfileFormValues(toValue(options.initialValues)),
    );

    const { errors, handleSubmit, isSubmitting, meta, resetForm, setFieldValue, values } =
        usePrimeForm<ProfileFormValues>({
            schema: profileFormSchema,
            initialValues: resolvedInitialValues.value,
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

    const isSubmitDisabled = computed(() => isSubmitting.value || !meta.value.valid);

    const submitForm = handleSubmit(
        (submittedValues): ProfileWritePayload => mapProfileFormToWritePayload(submittedValues),
    );

    return {
        name,
        email,
        phoneNumber,
        password,
        passwordConfirmation,
        errors,
        meta,
        isSubmitting,
        isSubmitDisabled,
        submitForm,
    };
}
