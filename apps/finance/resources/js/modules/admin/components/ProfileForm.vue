<script setup lang="ts">
import Message from 'primevue/message';
import { watch } from 'vue';

import { useProfileForm } from '@/modules/admin/composables/useProfileForm';
import type { Profile, ProfileWritePayload } from '@/modules/admin/schemas/profileSchemas';
import { AppInput, AppPasswordInput, AppText } from '@/modules/shared/components';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';

type FormState = {
    canSubmit: boolean;
    isSubmitting: boolean;
};

const props = withDefaults(
    defineProps<{
        formId?: string;
        initialValues?: Partial<Profile> | null;
        serverError?: string | null;
    }>(),
    {
        formId: 'profile-form',
        initialValues: null,
        serverError: null,
    },
);

const emit = defineEmits<{
    submit: [payload: ProfileWritePayload];
    stateChange: [payload: FormState];
}>();

const {
    name,
    email,
    password,
    passwordConfirmation,
    isSubmitting,
    isSubmitDisabled,
    meta,
    submitForm,
} = useProfileForm({
    initialValues: () => props.initialValues,
});
const { error: nameError, touch: touchName } = useFormFieldInteraction('name');
const { error: emailError, touch: touchEmail } = useFormFieldInteraction('email');
const { error: passwordError, touch: touchPassword } = useFormFieldInteraction('password');
const { error: passwordConfirmationError, touch: touchPasswordConfirmation } =
    useFormFieldInteraction('passwordConfirmation');

watch(
    [isSubmitDisabled, isSubmitting, meta],
    () => {
        emit('stateChange', {
            canSubmit: !isSubmitDisabled.value,
            isSubmitting: isSubmitting.value,
        });
    },
    { immediate: true, deep: true },
);

async function handleSubmit(): Promise<void> {
    const payload = await submitForm();

    if (!payload) {
        return;
    }

    emit('submit', payload);
}
</script>

<template>
    <form :id="props.formId" class="space-y-6" @submit.prevent="handleSubmit">
        <Message v-if="props.serverError" severity="error">{{ props.serverError }}</Message>

        <section class="space-y-4">
            <AppInput
                id="profile-name"
                v-model="name"
                label="Nombre"
                placeholder="Tu nombre completo"
                :error="nameError"
                @blur="touchName"
                required
            />

            <AppInput
                id="profile-email"
                v-model="email"
                label="Correo"
                type="email"
                placeholder="tu@correo.com"
                :error="emailError"
                @blur="touchEmail"
                required
            />
        </section>

        <section
            class="space-y-4 border-t pt-6"
            :style="{ borderColor: 'var(--app-color-border)' }"
        >
            <div class="space-y-1">
                <p class="text-sm font-semibold text-(--app-color-text)">Seguridad</p>
                <AppText size="sm">
                    Deja la contraseña vacía si no quieres actualizarla en este momento.
                </AppText>
            </div>

            <AppPasswordInput
                id="profile-password"
                v-model="password"
                label="Contraseña"
                placeholder="Nueva contraseña"
                autocomplete="new-password"
                :error="passwordError"
                @blur="touchPassword"
            />

            <AppPasswordInput
                id="profile-password-confirmation"
                v-model="passwordConfirmation"
                label="Confirmar contraseña"
                placeholder="Confirma la nueva contraseña"
                autocomplete="new-password"
                :error="passwordConfirmationError"
                @blur="touchPasswordConfirmation"
            />
        </section>
    </form>
</template>
