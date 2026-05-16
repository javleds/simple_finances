<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';

import { ApiError } from '@/lib/api/apiClient';
import { createAuthRepository } from '@/modules/auth/repositories/authRepository';
import { useLoginForm } from '@/modules/auth/composables/useLoginForm';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';
import { THEME_MODE, useThemeStore, type ThemeMode } from '@/stores/theme';
import {
  AppButton,
  AppCard,
  AppInput,
  AppLink,
  AppPasswordInput,
  AppText,
  AppToggleButton,
  AppTitle,
} from '@/modules/shared/components';

const themeStore = useThemeStore();
const router = useRouter();
const authRepository = createAuthRepository();

const themeOptions = [
  { value: THEME_MODE.LIGHT, label: 'Light' },
  { value: THEME_MODE.DARK, label: 'Dark' },
] as const;

const { email, password, isSubmitting, isSubmitDisabled, submitForm } = useLoginForm();
const { error: emailError, touch: touchEmail } = useFormFieldInteraction('email');
const { error: passwordError, touch: touchPassword } = useFormFieldInteraction('password');
const submitError = ref<string | null>(null);

function updateTheme(nextTheme: string): void {
  themeStore.setTheme(nextTheme as ThemeMode);
}

async function handleSubmit(): Promise<void> {
  submitError.value = null;

  const payload = await submitForm();

  if (!payload) {
    return;
  }

  try {
    const session = await authRepository.login(payload);

    if (session.user.isEmailVerified) {
      await router.push({ name: 'admin.dashboard' });
      return;
    }

    await router.push({
      name: 'auth.email-verification-required',
      query: { email: session.user.email },
    });
  } catch (error) {
    if (error instanceof ApiError) {
      if (error.status === 401 || error.status === 422) {
        submitError.value = 'Las credenciales no son correctas. Verifica tu correo y contraseña.';
        return;
      }

      if (error.status >= 500) {
        submitError.value =
          'No fue posible iniciar sesión por un problema del servidor. Intenta de nuevo en unos minutos.';
        return;
      }

      submitError.value = error.message || 'No fue posible iniciar sesión.';
      return;
    }

    submitError.value = 'No fue posible iniciar sesión. Revisa tu conexión e inténtalo de nuevo.';
  }
}
</script>

<template>
  <section
    class="relative flex min-h-screen items-center justify-center overflow-hidden bg-(--app-color-page) px-4 py-10 text-(--app-color-text) sm:px-6 lg:px-8"
  >
    <div
      class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,var(--app-color-page-glow),transparent_48%)]"
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
            <AppTitle as="h1" size="md">Iniciar sesión</AppTitle>
            <AppText>Ingresa con tus credenciales para continuar.</AppText>
          </div>

          <form class="space-y-5" @submit.prevent="handleSubmit">
            <section
              v-if="submitError"
              class="rounded-xl border border-(--app-color-danger) px-4 py-3"
            >
              <AppText size="sm" class="text-(--app-color-danger)!">
                {{ submitError }}
              </AppText>
            </section>

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

            <div class="space-y-2">
              <AppPasswordInput
                id="password"
                label="Contraseña"
                v-model="password"
                placeholder="Ingresa tu contraseña"
                autocomplete="current-password"
                :error="passwordError"
                @blur="touchPassword"
              />

              <div class="flex items-center justify-end gap-4">
                <AppLink :to="{ name: 'auth.password-recovery' }" variant="subtle" class="text-sm">
                  ¿Olvidaste tu contraseña?
                </AppLink>
              </div>
            </div>

            <AppButton
              type="submit"
              variant="primary"
              full-width
              :disabled="isSubmitDisabled || isSubmitting"
            >
              {{ isSubmitting ? 'Ingresando...' : 'Iniciar sesión' }}
            </AppButton>
          </form>

          <div class="border-t pt-5" :style="{ borderColor: 'var(--app-color-border)' }">
            <AppText>
              ¿Aún no tienes acceso?
              <AppLink :to="{ name: 'auth.register' }" variant="primary" class="font-semibold">
                Crear cuenta
              </AppLink>
            </AppText>
          </div>
        </div>
      </AppCard>
    </div>
  </section>
</template>
