<script setup lang="ts">
import { computed } from 'vue';
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { clearPendingVerificationEmail } from '@/modules/auth/lib/authSession';
import { createAuthRepository } from '@/modules/auth/repositories/authRepository';
import { usePasswordResetForm } from '@/modules/auth/composables/usePasswordResetForm';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';
import {
  AppButton,
  AppCard,
  AppLink,
  AppPasswordInput,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

const router = useRouter();
const route = useRoute();
const authRepository = createAuthRepository();
const serverError = ref<string | null>(null);

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
const isResetPending = ref(false);

async function handleSubmit(): Promise<void> {
  if (isResetPending.value) {
    return;
  }

  serverError.value = null;

  const payload = await submitForm();

  if (!payload) {
    return;
  }

  isResetPending.value = true;

  try {
    await authRepository.resetPassword(payload);
    clearPendingVerificationEmail();
    await router.push({ name: 'auth.login' });
  } catch (error) {
    serverError.value =
      error instanceof Error ? error.message : 'No fue posible restablecer la contraseña.';
  } finally {
    isResetPending.value = false;
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
            <AppTitle as="h1" size="md">Restablecer contraseña</AppTitle>
            <AppText>
              Define una nueva contraseña para la cuenta asociada al enlace de
              recuperación.
            </AppText>
          </div>

          <form class="space-y-4 sm:space-y-5" @submit.prevent="handleSubmit">
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
              :loading="isSubmitting || isResetPending"
              :disabled="isSubmitDisabled || isSubmitting || isResetPending"
            >
              {{
                isSubmitting || isResetPending
                  ? 'Actualizando...'
                  : 'Actualizar contraseña'
              }}
            </AppButton>
          </form>

          <div class="border-t pt-5" :style="{ borderColor: 'var(--app-color-border)' }">
            <AppText>
              ¿Recordaste tu contraseña?
              {{ ' ' }}
              <AppLink
                :to="{ name: 'auth.login' }"
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
