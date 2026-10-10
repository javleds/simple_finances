<script setup lang="ts">
import { ref } from 'vue';

import { createAuthRepository } from '@/modules/auth/repositories/authRepository';
import { usePasswordRecoveryForm } from '@/modules/auth/composables/usePasswordRecoveryForm';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';
import {
  AppButton,
  AppCard,
  AppInput,
  AppLink,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

const authRepository = createAuthRepository();
const recoveryMessage = ref<string | null>(null);

const { email, isSubmitting, isSubmitDisabled, submitForm } = usePasswordRecoveryForm();
const { error: emailError, touch: touchEmail } = useFormFieldInteraction('email');
const isRecoveryPending = ref(false);

async function handleSubmit(): Promise<void> {
  if (isRecoveryPending.value) {
    return;
  }

  const payload = await submitForm();

  if (!payload) {
    return;
  }

  isRecoveryPending.value = true;

  try {
    recoveryMessage.value = await authRepository.requestPasswordRecovery(payload);
  } finally {
    isRecoveryPending.value = false;
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
            <AppTitle as="h1" size="md">Recuperar contraseña</AppTitle>
            <AppText>
              Ingresa tu correo electrónico y te enviaremos instrucciones para
              restablecer tu contraseña.
            </AppText>
          </div>

          <form class="space-y-4 sm:space-y-5" @submit.prevent="handleSubmit">
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

            <AppText
              v-if="recoveryMessage"
              size="sm"
              class="text-(--app-color-success)!"
            >
              {{ recoveryMessage }}
            </AppText>

            <AppText size="sm" tone="subtle">
              Si existe una cuenta asociada a ese correo, recibirás un enlace para
              continuar con la recuperación.
            </AppText>

            <AppButton
              type="submit"
              variant="primary"
              full-width
              :loading="isSubmitting || isRecoveryPending"
              :disabled="isSubmitDisabled || isSubmitting || isRecoveryPending"
            >
              {{
                isSubmitting || isRecoveryPending
                  ? 'Enviando...'
                  : 'Recuperar mi contraseña'
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
