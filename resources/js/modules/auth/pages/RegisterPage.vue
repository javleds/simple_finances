<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { ApiError } from '@/lib/api/apiClient';
import AuthFeedbackBanner from '@/modules/auth/components/AuthFeedbackBanner.vue';
import { useLegalDocuments } from '@/modules/auth/legal/legalDocuments';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';
import RegisterLegalFields from '@/modules/auth/components/RegisterLegalFields.vue';
import RegisterLegalPreview, {
    type RegisterLegalDocument,
} from '@/modules/auth/components/RegisterLegalPreview.vue';
import { createAuthRepository } from '@/modules/auth/repositories/authRepository';
import { useRegisterForm } from '@/modules/auth/composables/useRegisterForm';
import {
    resolvePostAuthAction,
    resolvePostAuthRedirectRoute,
} from '@/modules/auth/lib/postAuthRedirect';
import type { AuthSession } from '@/modules/auth/schemas/authSchemas';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';
import {
    AppButton,
    AppCard,
    AppInput,
    AppLink,
    AppPasswordInput,
    AppText,
    AppTitle,
} from '@/modules/shared/components';

const route = useRoute();
const router = useRouter();
const authRepository = createAuthRepository();
const activeDocument = ref<RegisterLegalDocument | null>(null);

const {
    name,
    email,
    password,
    passwordConfirmation,
    isAdult,
    termsAccepted,
    privacyPolicyAccepted,
    isSubmitting,
    isSubmitDisabled,
    submitForm,
} = useRegisterForm();
const { error: nameError, touch: touchName } = useFormFieldInteraction('name');
const { error: emailError, touch: touchEmail } = useFormFieldInteraction('email');
const { error: passwordError, touch: touchPassword } = useFormFieldInteraction('password');
const { error: passwordConfirmationError, touch: touchPasswordConfirmation } =
    useFormFieldInteraction('passwordConfirmation');
const { error: termsAcceptedError, touch: touchTermsAccepted } =
    useFormFieldInteraction('termsAccepted');
const { error: privacyPolicyAcceptedError, touch: touchPrivacyPolicyAccepted } =
    useFormFieldInteraction('privacyPolicyAccepted');
const isRegisterPending = ref(false);
const feedback = ref('');
const {
    data: legalDocuments,
    isError: legalError,
    refetch: refreshLegalDocuments,
} = useLegalDocuments();
const legalAvailable = computed(
    () => legalDocuments.value?.available === true && !legalError.value,
);
const { error: adultError, touch: touchAdult } = useFormFieldInteraction('isAdult');
watch(
    () => [
        legalDocuments.value?.documents.terms.hash,
        legalDocuments.value?.documents.privacy.hash,
    ],
    () => {
        termsAccepted.value = false;
        privacyPolicyAccepted.value = false;
        isAdult.value = false;
    },
);

watch(
    () => route.query.email,
    (nextEmail) => {
        if (typeof nextEmail === 'string') {
            email.value = nextEmail;
        }
    },
    { immediate: true },
);

async function openDocument(document: RegisterLegalDocument): Promise<void> {
    activeDocument.value = document;
    await nextTick();
    const preview = window.document.getElementById('register-legal-document');
    preview?.focus({ preventScroll: true });
    preview?.scrollIntoView({ block: 'nearest' });
}

async function closeDocument(): Promise<void> {
    const previousDocument = activeDocument.value;
    activeDocument.value = null;
    await nextTick();
    window.document.getElementById(`register-${previousDocument}-link`)?.focus();
}

async function navigateAfterRegister(session: AuthSession): Promise<void> {
    if (!session.user.isEmailVerified) {
        await router.push({
            name: 'auth.email-verification-required',
            query: { email: session.user.email },
        });
        return;
    }

    const postAuthRedirectRoute = resolvePostAuthRedirectRoute(session.postAuthRedirect, router);

    if (postAuthRedirectRoute) {
        await router.push(postAuthRedirectRoute);
        return;
    }

    await router.push({ name: 'admin.dashboard' });
}

async function handleSubmit(): Promise<void> {
    if (isRegisterPending.value || isSubmitting.value || !legalAvailable.value) return;
    isRegisterPending.value = true;
    feedback.value = '';
    const revisions = legalDocuments.value?.documents;
    try {
        const payload = await submitForm();
        if (
            !payload ||
            !revisions ||
            !legalDocuments.value ||
            !legalAvailable.value ||
            !termsAccepted.value ||
            !privacyPolicyAccepted.value ||
            !isAdult.value
        )
            return;
        if (
            revisions.terms.hash !== legalDocuments.value.documents.terms.hash ||
            revisions.privacy.hash !== legalDocuments.value.documents.privacy.hash
        ) {
            feedback.value = 'Los documentos cambiaron. Revísalos y vuelve a aceptar.';
            return;
        }
        const session = await authRepository.register({
            ...payload,
            termsHash: revisions.terms.hash,
            privacyHash: revisions.privacy.hash,
            postAuthAction: resolvePostAuthAction(route.query.post_auth_action),
        });
        await navigateAfterRegister(session);
    } catch (error) {
        feedback.value = resolveApiErrorMessage(
            error,
            'No pudimos crear tu cuenta. Intenta nuevamente.',
        );
        if (error instanceof ApiError && error.status === 409) {
            termsAccepted.value = false;
            privacyPolicyAccepted.value = false;
            isAdult.value = false;
            await refreshLegalDocuments();
        }
    } finally {
        isRegisterPending.value = false;
    }
}
</script>

<template>
    <section
        class="relative flex min-h-dvh items-start justify-center bg-(--app-color-page) px-4 pt-20 pb-8 text-(--app-color-text) sm:items-center sm:px-6 sm:pt-24 sm:pb-10 lg:px-8"
    >
        <div class="w-full max-w-md">
            <AppCard
                :padded="false"
                class="border-0! bg-transparent! shadow-none! sm:border! sm:bg-(--app-color-surface)! sm:p-8!"
            >
                <div class="space-y-6">
                    <div class="space-y-2">
                        <AppTitle as="h1" size="md">Crear cuenta</AppTitle>
                        <AppText>Completa tus datos para registrarte y comenzar.</AppText>
                    </div>

                    <AuthFeedbackBanner v-if="feedback" :message="feedback" />
                    <div v-if="!legalAvailable" role="alert" class="space-y-3">
                        <AppText
                            >La información legal debe estar disponible para completar el
                            registro.</AppText
                        >
                        <AppButton
                            type="button"
                            variant="secondary"
                            @click="refreshLegalDocuments()"
                            >Reintentar</AppButton
                        >
                    </div>
                    <form class="space-y-4 sm:space-y-5" @submit.prevent="handleSubmit">
                        <AppInput
                            id="name"
                            v-model="name"
                            type="text"
                            label="Nombre"
                            placeholder="Tu nombre completo"
                            autocomplete="name"
                            :error="nameError"
                            @blur="touchName"
                            required
                        />

                        <AppInput
                            id="email"
                            v-model="email"
                            type="email"
                            label="Correo electrónico"
                            placeholder="nombre@empresa.com"
                            autocomplete="email"
                            :error="emailError"
                            @blur="touchEmail"
                            required
                        />

                        <AppPasswordInput
                            id="password"
                            v-model="password"
                            label="Contraseña"
                            placeholder="Crea una contraseña"
                            autocomplete="new-password"
                            :error="passwordError"
                            @blur="touchPassword"
                        />

                        <AppPasswordInput
                            id="password-confirmation"
                            v-model="passwordConfirmation"
                            label="Confirmar contraseña"
                            placeholder="Repite tu contraseña"
                            autocomplete="new-password"
                            :error="passwordConfirmationError"
                            @blur="touchPasswordConfirmation"
                        />

                        <RegisterLegalFields
                            v-model:is-adult="isAdult"
                            :adult-error="adultError"
                            @touch-adult="touchAdult"
                            v-model:privacy-policy-accepted="privacyPolicyAccepted"
                            v-model:terms-accepted="termsAccepted"
                            :active-document="activeDocument"
                            :privacy-policy-error="privacyPolicyAcceptedError"
                            :terms-error="termsAcceptedError"
                            @open-privacy="openDocument('privacy')"
                            @open-terms="openDocument('terms')"
                            @touch-privacy-policy="touchPrivacyPolicyAccepted"
                            @touch-terms="touchTermsAccepted"
                        />

                        <RegisterLegalPreview
                            v-if="activeDocument"
                            :document="activeDocument"
                            @close="closeDocument"
                        />

                        <AppButton
                            type="submit"
                            variant="primary"
                            full-width
                            :loading="isSubmitting || isRegisterPending"
                            :disabled="
                                !legalAvailable ||
                                isSubmitDisabled ||
                                isSubmitting ||
                                isRegisterPending
                            "
                        >
                            {{
                                isSubmitting || isRegisterPending ? 'Registrando...' : 'Registrarme'
                            }}
                        </AppButton>
                    </form>

                    <div class="border-t pt-5" :style="{ borderColor: 'var(--app-color-border)' }">
                        <AppText>
                            ¿Ya tienes una cuenta?
                            {{ ' ' }}
                            <AppLink
                                :to="{ name: 'auth.login', query: route.query }"
                                variant="primary"
                                class="font-semibold"
                            >
                                Volver al login
                            </AppLink>
                        </AppText>
                    </div>
                </div>
            </AppCard>
        </div>
    </section>
</template>
