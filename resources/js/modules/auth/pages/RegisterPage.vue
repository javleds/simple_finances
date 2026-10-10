<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import RegisterLegalFields from '@/modules/auth/components/RegisterLegalFields.vue';
import RegisterLegalModals, {
  type RegisterLegalDocument,
} from '@/modules/auth/components/RegisterLegalModals.vue';
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
const activeDocument = ref<RegisterLegalDocument>(null);

const {
  name,
  email,
  password,
  passwordConfirmation,
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

watch(
  () => route.query.email,
  (nextEmail) => {
    if (typeof nextEmail === 'string') {
      email.value = nextEmail;
    }
  },
  { immediate: true },
);

function openDocument(document: Exclude<RegisterLegalDocument, null>): void {
  activeDocument.value = document;
}

function closeDocument(): void {
  activeDocument.value = null;
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
  if (isRegisterPending.value) {
    return;
  }

  const payload = await submitForm();

  if (!payload) {
    return;
  }

  isRegisterPending.value = true;

  try {
    const session = await authRepository.register({
      ...payload,
      postAuthAction: resolvePostAuthAction(route.query.post_auth_action),
    });
    await navigateAfterRegister(session);
  } finally {
    isRegisterPending.value = false;
  }
}
</script>

<template>
  <section
    class="relative flex min-h-screen items-center justify-center overflow-hidden bg-(--app-color-page) px-4 pt-24 pb-10 text-(--app-color-text) sm:px-6 lg:px-8"
  >
    <div
      class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_var(--app-color-page-glow),_transparent_48%)]"
    />

    <div class="w-full max-w-md">
      <AppCard>
        <div class="space-y-6">
          <div class="space-y-2">
            <AppTitle as="h1" size="md">Crear cuenta</AppTitle>
            <AppText>Completa tus datos para registrarte y comenzar.</AppText>
          </div>

          <form class="space-y-5" @submit.prevent="handleSubmit">
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
              v-model:privacy-policy-accepted="privacyPolicyAccepted"
              v-model:terms-accepted="termsAccepted"
              :privacy-policy-error="privacyPolicyAcceptedError"
              :terms-error="termsAcceptedError"
              @open-privacy="openDocument('privacy')"
              @open-terms="openDocument('terms')"
              @touch-privacy-policy="touchPrivacyPolicyAccepted"
              @touch-terms="touchTermsAccepted"
            />

            <AppButton
              type="submit"
              variant="primary"
              full-width
              :loading="isSubmitting || isRegisterPending"
              :disabled="isSubmitDisabled || isSubmitting || isRegisterPending"
            >
              {{ isSubmitting || isRegisterPending ? 'Registrando...' : 'Registrarme' }}
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

    <RegisterLegalModals :active-document="activeDocument" @close="closeDocument" />
  </section>
</template>
