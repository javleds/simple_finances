<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { createProfileRepository } from '@/modules/admin/repositories/profileRepository';
import { getStoredAuthToken } from '@/lib/api/apiClient';
import { useRouter } from 'vue-router';
import { useRoute } from 'vue-router';

import { createAuthRepository } from '@/modules/auth/repositories/authRepository';
import {
  getPendingVerificationEmail,
  getStoredAuthSession,
  setPendingVerificationEmail,
  setStoredAuthSession,
} from '@/modules/auth/lib/authSession';
import { resolvePostAuthRedirectRoute } from '@/modules/auth/lib/postAuthRedirect';
import {
  AppButton,
  AppCard,
  AppLink,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

const route = useRoute();
const router = useRouter();
const authRepository = createAuthRepository();
const profileRepository = createProfileRepository();
const isSubmitting = ref(false);
const isCheckingVerification = ref(true);
const message = ref<string | null>(null);

const email = computed(() => {
  const emailFromQuery = route.query.email;

  if (typeof emailFromQuery === 'string' && emailFromQuery.trim()) {
    return emailFromQuery.trim();
  }

  return getPendingVerificationEmail();
});

async function checkVerificationStatus(): Promise<void> {
  const authToken = getStoredAuthToken();
  const storedSession = getStoredAuthSession();

  if (!authToken) {
    isCheckingVerification.value = false;
    return;
  }

  try {
    const profile = await profileRepository.get();

    if (!profile.isEmailVerified) {
      setPendingVerificationEmail(profile.email);
      return;
    }

    const verifiedSession = {
      token: storedSession?.token ?? authToken,
      tokenType: storedSession?.tokenType ?? 'Bearer',
      expiresAt: storedSession?.expiresAt ?? '',
      postAuthRedirect: storedSession?.postAuthRedirect ?? null,
      user: {
        id: storedSession?.user.id ?? profile.id,
        name: profile.name,
        email: profile.email,
        phoneNumber: profile.phoneNumber,
        emailVerifiedAt: storedSession?.user.emailVerifiedAt ?? null,
        telegramChatId: profile.telegramChatId,
        isEmailVerified: profile.isEmailVerified,
      },
    };

    setStoredAuthSession(verifiedSession);

    const postAuthRedirectRoute = resolvePostAuthRedirectRoute(
      verifiedSession.postAuthRedirect,
      router,
    );

    await router.push(postAuthRedirectRoute ?? { name: 'admin.dashboard' });
  } catch {
    // Keep the user on this screen when the profile check cannot be completed.
  } finally {
    isCheckingVerification.value = false;
  }
}

async function handleResendEmail(): Promise<void> {
  if (!email.value || isSubmitting.value) {
    return;
  }

  isSubmitting.value = true;

  try {
    message.value = await authRepository.resendEmailVerificationByEmail(email.value);
  } finally {
    isSubmitting.value = false;
  }
}

async function handleBackToLogin(): Promise<void> {
  if (isSubmitting.value || isCheckingVerification.value) {
    return;
  }

  isSubmitting.value = true;

  try {
    await authRepository.logout();
  } finally {
    isSubmitting.value = false;
    await router.push({ name: 'auth.login' });
  }
}

onMounted(() => {
  void checkVerificationStatus();
});
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
          <div class="space-y-2">
            <AppTitle as="h1" size="md">Verificación de email requerida</AppTitle>
            <AppText>
              Antes de entrar a la app necesitas verificar tu correo electrónico.
            </AppText>
          </div>

          <div
            class="rounded-xl border bg-(--app-color-surface-muted) px-4 py-4"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <AppText v-if="email">
              Enviamos la verificación a
              <span class="font-semibold text-(--app-color-text)">{{ email }}</span>.
            </AppText>
            <AppText v-else>
              No encontramos el correo de esta sesión. Vuelve a iniciar sesión o regístrate otra
              vez para solicitar un nuevo correo de verificación.
            </AppText>
          </div>

          <AppText v-if="message" size="sm" class="text-(--app-color-success)!">
            {{ message }}
          </AppText>

          <div class="space-y-3">
            <AppButton
              type="button"
              variant="primary"
              full-width
              :disabled="!email || isSubmitting || isCheckingVerification"
              @click="handleResendEmail"
            >
              {{
                isCheckingVerification
                  ? 'Validando verificación...'
                  : isSubmitting
                    ? 'Enviando verificación...'
                    : 'Reenviar correo de verificación'
              }}
            </AppButton>

            <AppLink
              href=""
              variant="secondary"
              class="justify-center"
              @click.prevent="handleBackToLogin"
            >
              Volver al login
            </AppLink>
          </div>
        </div>
      </AppCard>
    </div>
  </section>
</template>
