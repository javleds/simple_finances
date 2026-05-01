<script setup lang="ts">
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

const themeOptions = [
  { value: THEME_MODE.LIGHT, label: 'Light' },
  { value: THEME_MODE.DARK, label: 'Dark' },
] as const;

function updateTheme(nextTheme: string): void {
  themeStore.setTheme(nextTheme as ThemeMode);
}
</script>

<template>
  <section
    class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[var(--app-color-page)] px-4 py-10 text-[var(--app-color-text)] sm:px-6 lg:px-8"
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
                <AppText as="div" tone="muted" class="font-medium text-[var(--app-color-text)]">
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

          <form class="space-y-5">
            <AppInput
              id="email"
              type="email"
              label="Correo electrónico"
              placeholder="nombre@empresa.com"
              autocomplete="email"
            />

            <AppText size="sm" tone="subtle">
              Si existe una cuenta asociada a ese correo, recibirás un enlace para continuar con la
              recuperación.
            </AppText>

            <AppButton type="submit" variant="primary" full-width>
              Recuperar mi contraseña
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
