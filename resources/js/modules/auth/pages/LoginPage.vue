<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { ApiError } from '@/lib/api/apiClient';
import AuthFeedbackBanner from '@/modules/auth/components/AuthFeedbackBanner.vue';
import { createAuthRepository } from '@/modules/auth/repositories/authRepository';
import { useLoginForm } from '@/modules/auth/composables/useLoginForm';
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

const { email, password, isSubmitting, isSubmitDisabled, submitForm } = useLoginForm();
const { error: emailError, touch: touchEmail } = useFormFieldInteraction('email');
const { error: passwordError, touch: touchPassword } = useFormFieldInteraction('password');
const submitError = ref<string | null>(null);
const isLoginPending = ref(false);

watch(
  () => route.query.email,
  (nextEmail) => {
    if (typeof nextEmail === 'string') {
      email.value = nextEmail;
    }
  },
  { immediate: true },
);

async function navigateAfterLogin(session: AuthSession): Promise<void> {
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
  if (isLoginPending.value) {
    return;
  }

  submitError.value = null;

  const payload = await submitForm();

  if (!payload) {
    return;
  }

  isLoginPending.value = true;

  try {
    const session = await authRepository.login({
      ...payload,
      postAuthAction: resolvePostAuthAction(route.query.post_auth_action),
    });
    await navigateAfterLogin(session);
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
  } finally {
    isLoginPending.value = false;
  }
}
</script>

<template>
  <section
    class="relative flex min-h-screen items-center justify-center overflow-hidden bg-(--app-color-page) px-4 pt-24 pb-10 text-(--app-color-text) sm:px-6 lg:px-8"
  >
    <div
      class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,var(--app-color-page-glow),transparent_48%)]"
    />

    <div class="w-full max-w-md">
      <AppCard>
        <div class="space-y-6">
          <div class="space-y-2">
            <AppTitle as="h1" size="md">Iniciar sesión</AppTitle>
            <AppText>Ingresa con tus credenciales para continuar.</AppText>
          </div>

          <form class="space-y-5" @submit.prevent="handleSubmit">
            <AuthFeedbackBanner v-if="submitError" :message="submitError" tone="danger" />

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
              :loading="isSubmitting || isLoginPending"
              :disabled="isSubmitDisabled || isSubmitting || isLoginPending"
            >
              {{ isSubmitting || isLoginPending ? 'Ingresando...' : 'Iniciar sesión' }}
            </AppButton>
          </form>

          <div class="border-t pt-5" :style="{ borderColor: 'var(--app-color-border)' }">
            <AppText>
              ¿Aún no tienes acceso?
              <AppLink
                :to="{ name: 'auth.register', query: route.query }"
                variant="primary"
                class="font-semibold"
              >
                Crear cuenta
              </AppLink>
            </AppText>
          </div>
        </div>
      </AppCard>
    </div>
  </section>
</template>
