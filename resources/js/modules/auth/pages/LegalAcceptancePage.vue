<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ApiError } from '@/lib/api/apiClient';
import AuthFeedbackBanner from '../components/AuthFeedbackBanner.vue';
import RegisterLegalFields from '../components/RegisterLegalFields.vue';
import {
    acceptLegalDocuments,
    safeLegalRedirect,
    useLegalDocuments,
} from '../legal/legalDocuments';
import { createAuthRepository } from '../repositories/authRepository';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';
import { AppButton, AppCard, AppText, AppTitle } from '@/modules/shared/components';
const route = useRoute();
const router = useRouter();
const { data, isError, refetch } = useLegalDocuments();
const termsAccepted = ref(false);
const privacyPolicyAccepted = ref(false);
const isAdult = ref(false);
const pending = ref(false);
const feedback = ref('');
const available = computed(() => data.value?.available === true && !isError.value);
function resetConsent(): void {
    termsAccepted.value = false;
    privacyPolicyAccepted.value = false;
    isAdult.value = false;
}
watch(() => [data.value?.documents.terms.hash, data.value?.documents.privacy.hash], resetConsent);
async function submit(): Promise<void> {
    if (
        pending.value ||
        !available.value ||
        !data.value ||
        !termsAccepted.value ||
        !privacyPolicyAccepted.value ||
        !isAdult.value
    )
        return;
    pending.value = true;
    feedback.value = '';
    const documents = data.value.documents;
    try {
        await acceptLegalDocuments({
            termsAccepted: termsAccepted.value,
            privacyPolicyAccepted: privacyPolicyAccepted.value,
            isAdult: isAdult.value,
            termsHash: documents.terms.hash,
            privacyHash: documents.privacy.hash,
        });
        await router.replace(safeLegalRedirect(route.query.redirect));
    } catch (error) {
        feedback.value = resolveApiErrorMessage(
            error,
            'No pudimos guardar tu aceptación. Intenta nuevamente.',
        );
        if (error instanceof ApiError && error.status === 409) {
            resetConsent();
            await refetch();
        }
    } finally {
        pending.value = false;
    }
}
async function logout(): Promise<void> {
    if (pending.value) return;
    pending.value = true;
    try {
        await createAuthRepository().logout();
    } finally {
        pending.value = false;
        await router.replace({ name: 'auth.login' });
    }
}
</script>
<template>
    <section class="mx-auto w-full max-w-2xl px-4 pt-24 pb-8">
        <AppCard>
            <div class="space-y-5">
                <AppTitle as="h1">Actualización de información legal</AppTitle>
                <AppText
                    >Revisa los términos y el aviso de privacidad vigentes para continuar usando
                    Fin-si. Puedes consultar los documentos y cerrar sesión antes de
                    decidir.</AppText
                >
                <AuthFeedbackBanner v-if="feedback" :message="feedback" />
                <div v-if="!available" role="alert" class="space-y-3">
                    <AppText
                        >La información legal no está disponible temporalmente. Tu aceptación no se
                        ha guardado.</AppText
                    >
                    <AppButton
                        type="button"
                        variant="secondary"
                        :disabled="pending"
                        @click="refetch()"
                        >Reintentar</AppButton
                    >
                </div>
                <form class="space-y-5" @submit.prevent="submit">
                    <RegisterLegalFields
                        v-model:terms-accepted="termsAccepted"
                        v-model:privacy-policy-accepted="privacyPolicyAccepted"
                        v-model:is-adult="isAdult"
                    />
                    <div
                        class="flex flex-col gap-3 border-t border-(--app-color-border) pt-5 sm:flex-row-reverse"
                    >
                        <AppButton
                            type="submit"
                            class="w-full sm:w-auto sm:min-w-40"
                            :loading="pending"
                            :disabled="
                                pending ||
                                !available ||
                                !termsAccepted ||
                                !privacyPolicyAccepted ||
                                !isAdult
                            "
                            >Aceptar y continuar</AppButton
                        >
                        <AppButton
                            type="button"
                            variant="outline"
                            class="w-full sm:w-auto sm:min-w-40"
                            :disabled="pending"
                            @click="logout"
                            >Cerrar sesión</AppButton
                        >
                    </div>
                </form>
            </div>
        </AppCard>
    </section>
</template>
