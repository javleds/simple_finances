<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';

import PrivacyPolicyContent from '@/modules/auth/components/PrivacyPolicyContent.vue';
import TermsAndConditionsContent from '@/modules/auth/components/TermsAndConditionsContent.vue';
import { createAuthRepository } from '@/modules/auth/repositories/authRepository';
import { useRegisterForm } from '@/modules/auth/composables/useRegisterForm';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';
import { THEME_MODE, useThemeStore, type ThemeMode } from '@/stores/theme';
import {
  AppButton,
  AppCard,
  AppInput,
  AppLink,
  AppModal,
  AppPasswordInput,
  AppText,
  AppToggleButton,
  AppTitle,
} from '@/modules/shared/components';

type LegalDocument = 'terms' | 'privacy' | null;

const themeStore = useThemeStore();
const router = useRouter();
const authRepository = createAuthRepository();
const activeDocument = ref<LegalDocument>(null);

const themeOptions = [
  { value: THEME_MODE.LIGHT, label: 'Light' },
  { value: THEME_MODE.DARK, label: 'Dark' },
] as const;

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

function updateTheme(nextTheme: string): void {
  themeStore.setTheme(nextTheme as ThemeMode);
}

function openDocument(document: Exclude<LegalDocument, null>): void {
  activeDocument.value = document;
}

function closeDocument(): void {
  activeDocument.value = null;
}

async function handleSubmit(): Promise<void> {
  const payload = await submitForm();

  if (!payload) {
    return;
  }

  const session = await authRepository.register(payload);

  if (session.user.isEmailVerified) {
    await router.push({ name: 'admin.dashboard' });
    return;
  }

  await router.push({
    name: 'auth.email-verification-required',
    query: { email: session.user.email },
  });
}
</script>

<template>
  <section
    class="relative flex min-h-screen items-center justify-center overflow-hidden bg-(--app-color-page) px-4 py-10 text-(--app-color-text) sm:px-6 lg:px-8"
  >
    <div
      class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_var(--app-color-page-glow),_transparent_48%)]"
    />

    <div class="w-full max-w-md">
      <AppCard>
        <div class="space-y-6">
          <AppCard
            muted
            :padded="false"
            class="rounded-xl px-4 py-3"
            :style="{ borderColor: 'var(--app-color-border-strong)' }"
          >
            <div class="flex items-center justify-between gap-4">
              <div class="space-y-1">
                <AppText as="div" tone="muted" class="font-medium text-(--app-color-text)">
                  Tema visual
                </AppText>
                <AppText size="sm" tone="subtle"> Cambia entre light y dark mode. </AppText>
              </div>

              <AppToggleButton
                :model-value="themeStore.mode"
                :options="themeOptions"
                @update:model-value="updateTheme"
              />
            </div>
          </AppCard>

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

            <div
              class="space-y-3 rounded-xl border px-4 py-4"
              :style="{ borderColor: 'var(--app-color-border)' }"
            >
              <label class="flex items-start gap-3">
                <input
                  v-model="termsAccepted"
                  type="checkbox"
                  class="mt-1 h-4 w-4 rounded border-(--app-color-input-border) text-(--app-color-primary) focus:ring-(--app-color-focus-ring)"
                  @change="touchTermsAccepted"
                />
                <span class="text-sm text-(--app-color-text)">
                  Acepto los
                  <AppLink
                    href=""
                    variant="primary"
                    class="font-semibold"
                    @click.prevent="openDocument('terms')"
                  >
                    términos y condiciones
                  </AppLink>
                </span>
              </label>
              <p v-if="termsAcceptedError" class="text-sm text-(--app-color-danger)">
                {{ termsAcceptedError }}
              </p>

              <label class="flex items-start gap-3">
                <input
                  v-model="privacyPolicyAccepted"
                  type="checkbox"
                  class="mt-1 h-4 w-4 rounded border-(--app-color-input-border) text-(--app-color-primary) focus:ring-(--app-color-focus-ring)"
                  @change="touchPrivacyPolicyAccepted"
                />
                <span class="text-sm text-(--app-color-text)">
                  Acepto la
                  <AppLink
                    href=""
                    variant="primary"
                    class="font-semibold"
                    @click.prevent="openDocument('privacy')"
                  >
                    política de privacidad
                  </AppLink>
                </span>
              </label>
              <p v-if="privacyPolicyAcceptedError" class="text-sm text-(--app-color-danger)">
                {{ privacyPolicyAcceptedError }}
              </p>
            </div>

            <AppButton
              type="submit"
              variant="primary"
              full-width
              :disabled="isSubmitDisabled || isSubmitting"
            >
              {{ isSubmitting ? 'Registrando...' : 'Registrarme' }}
            </AppButton>
          </form>

          <div class="border-t pt-5" :style="{ borderColor: 'var(--app-color-border)' }">
            <AppText>
              ¿Ya tienes una cuenta?
              {{ ' ' }}
              <AppLink :to="{ name: 'auth.login' }" variant="primary" class="font-semibold">
                Volver al login
              </AppLink>
            </AppText>
          </div>
        </div>
      </AppCard>
    </div>

    <AppModal
      :open="activeDocument === 'terms'"
      title="Términos y condiciones"
      close-label="Cerrar"
      @close="closeDocument"
    >
      <TermsAndConditionsContent />
    </AppModal>

    <AppModal
      :open="activeDocument === 'privacy'"
      title="Política de privacidad"
      close-label="Cerrar"
      @close="closeDocument"
    >
      <PrivacyPolicyContent />
    </AppModal>
  </section>
</template>
