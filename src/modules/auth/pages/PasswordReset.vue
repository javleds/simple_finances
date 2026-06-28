<script setup lang="ts">
import { computed } from 'vue';
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { clearPendingVerificationEmail } from '@/modules/auth/lib/authSession';
import { createAuthRepository } from '@/modules/auth/repositories/authRepository';
import { usePasswordResetForm } from '@/modules/auth/composables/usePasswordResetForm';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';
import { THEME_MODE, useThemeStore, type ThemeMode } from '@/stores/theme';
import {
  AppButton,
  AppCard,
  AppLink,
  AppPasswordInput,
  AppText,
  AppToggleButton,
  AppTitle,
} from '@/modules/shared/components';

const themeStore = useThemeStore();
const router = useRouter();
const route = useRoute();
const authRepository = createAuthRepository();
const serverError = ref<string | null>(null);

const themeOptions = [
  { value: THEME_MODE.LIGHT, label: 'Light' },
  { value: THEME_MODE.DARK, label: 'Dark' },
] as const;

const initialToken = computed(() =>
  typeof route.query.token === 'string' ? route.query.token : '',
);
const initialEmail = computed(() =>
  typeof route.query.email === 'string' ? route.query.email : '',
);

const { token, email, password, passwordConfirmation, isSubmitting, isSubmitDisabled, submitForm } =
  usePasswordResetForm({
    token: initialToken.value,
    email: initialEmail.value,
  });
const { error: passwordError, touch: touchPassword } = useFormFieldInteraction('password');
const { error: passwordConfirmationError, touch: touchPasswordConfirmation } =
  useFormFieldInteraction('passwordConfirmation');

function updateTheme(nextTheme: ThemeMode): void {
  themeStore.setTheme(nextTheme);
}

async function handleSubmit(): Promise<void> {
  serverError.value = null;

  const payload = await submitForm();

  if (!payload) {
    return;
  }

  try {
    await authRepository.resetPassword(payload);
    clearPendingVerificationEmail();
    await router.push({ name: 'auth.login' });
  } catch (error) {
    serverError.value =
      error instanceof Error ? error.message : 'No fue posible restablecer la contraseña.';
  }
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
            <AppTitle as="h1" size="md">Restablecer contraseña</AppTitle>
            <AppText>
              Define una nueva contraseña para la cuenta asociada al enlace de recuperación.
            </AppText>
          </div>

          <form class="space-y-5" @submit.prevent="handleSubmit">
            <section
              v-if="serverError"
              class="rounded-xl border border-(--app-color-danger) px-4 py-3"
            >
              <AppText size="sm" class="text-(--app-color-danger)!">
                {{ serverError }}
              </AppText>
            </section>

            <input v-model="token" type="hidden" />
            <input v-model="email" type="hidden" />

            <AppPasswordInput
              id="password"
              v-model="password"
              label="Nueva contraseña"
              placeholder="Nueva contraseña"
              autocomplete="new-password"
              :error="passwordError"
              @blur="touchPassword"
            />

            <AppPasswordInput
              id="password-confirmation"
              v-model="passwordConfirmation"
              label="Confirmar contraseña"
              placeholder="Confirma tu nueva contraseña"
              autocomplete="new-password"
              :error="passwordConfirmationError"
              @blur="touchPasswordConfirmation"
            />

            <AppButton
              type="submit"
              variant="primary"
              full-width
              :disabled="isSubmitDisabled || isSubmitting"
            >
              {{ isSubmitting ? 'Actualizando...' : 'Actualizar contraseña' }}
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
