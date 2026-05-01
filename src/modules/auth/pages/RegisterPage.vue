<script setup lang="ts">
import { ref } from 'vue';

import PrivacyPolicyContent from '@/modules/auth/components/PrivacyPolicyContent.vue';
import TermsAndConditionsContent from '@/modules/auth/components/TermsAndConditionsContent.vue';
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
const activeDocument = ref<LegalDocument>(null);

const themeOptions = [
  { value: THEME_MODE.LIGHT, label: 'Light' },
  { value: THEME_MODE.DARK, label: 'Dark' },
] as const;

function updateTheme(nextTheme: string): void {
  themeStore.setTheme(nextTheme as ThemeMode);
}

function openDocument(document: Exclude<LegalDocument, null>): void {
  activeDocument.value = document;
}

function closeDocument(): void {
  activeDocument.value = null;
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

          <form class="space-y-5">
            <AppInput
              id="name"
              type="text"
              label="Nombre"
              placeholder="Tu nombre completo"
              autocomplete="name"
            />

            <AppInput
              id="email"
              type="email"
              label="Correo electrónico"
              placeholder="nombre@empresa.com"
              autocomplete="email"
            />

            <AppPasswordInput
              id="password"
              label="Contraseña"
              placeholder="Crea una contraseña"
              autocomplete="new-password"
            />

            <AppPasswordInput
              id="password-confirmation"
              label="Confirmar contraseña"
              placeholder="Repite tu contraseña"
              autocomplete="new-password"
            />

            <AppText size="sm">
              Al registrarse, usted acepta los
              {{ ' ' }}
              <AppLink
                href=""
                variant="primary"
                class="font-semibold"
                @click.prevent="openDocument('terms')"
              >
                términos y condiciones
              </AppLink>
              {{ ' ' }}y la{{ ' ' }}
              <AppLink
                href=""
                variant="primary"
                class="font-semibold"
                @click.prevent="openDocument('privacy')"
              >
                política de privacidad
              </AppLink>
              .
            </AppText>

            <AppButton type="submit" variant="primary" full-width>Registrarme</AppButton>
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
