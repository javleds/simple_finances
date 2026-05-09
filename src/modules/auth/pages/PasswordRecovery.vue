<script setup lang="ts">
import { ref } from 'vue';

import { createAuthRepository } from '@/modules/auth/repositories/authRepository';
import { usePasswordRecoveryForm } from '@/modules/auth/composables/usePasswordRecoveryForm';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';
import { THEME_MODE, useThemeStore, type ThemeMode } from '@/stores/theme';
import {
  AppButton,
  AppCard,
  AppInput,
  AppLink,
  AppText,
  AppToggleButton,
  AppTitle,
} from '@/modules/shared/components';

const themeStore = useThemeStore();
const authRepository = createAuthRepository();
const recoveryMessage = ref<string | null>(null);

const themeOptions = [
  { value: THEME_MODE.LIGHT, label: 'Light' },
  { value: THEME_MODE.DARK, label: 'Dark' },
] as const;

const { email, isSubmitting, isSubmitDisabled, submitForm } = usePasswordRecoveryForm();
const { error: emailError, touch: touchEmail } = useFormFieldInteraction('email');

function updateTheme(nextTheme: string): void {
  themeStore.setTheme(nextTheme as ThemeMode);
}

async function handleSubmit(): Promise<void> {
  const payload = await submitForm();

  if (!payload) {
    return;
  }

  recoveryMessage.value = await authRepository.requestPasswordRecovery(payload);
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
            <AppTitle as="h1" size="md">Recuperar contraseña</AppTitle>
            <AppText>
              Ingresa tu correo electrónico y te enviaremos instrucciones para restablecer tu
              contraseña.
            </AppText>
          </div>

          <form class="space-y-5" @submit.prevent="handleSubmit">
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

            <AppText v-if="recoveryMessage" size="sm" class="text-(--app-color-success)!">
              {{ recoveryMessage }}
            </AppText>

            <AppText size="sm" tone="subtle">
              Si existe una cuenta asociada a ese correo, recibirás un enlace para continuar con la
              recuperación.
            </AppText>

            <AppButton
              type="submit"
              variant="primary"
              full-width
              :disabled="isSubmitDisabled || isSubmitting"
            >
              {{ isSubmitting ? 'Enviando...' : 'Recuperar mi contraseña' }}
            </AppButton>
          </form>

          <div class="border-t pt-5" :style="{ borderColor: 'var(--app-color-border)' }">
            <AppText>
              ¿Recordaste tu contraseña?
              {{ ' ' }}
              <AppLink :to="{ name: 'auth.login' }" variant="primary" class="font-semibold">
                Volver al login
              </AppLink>
            </AppText>
          </div>
        </div>
      </AppCard>
    </div>
  </section>
</template>
