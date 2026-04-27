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
            <AppTitle as="h1" size="md">Iniciar sesión</AppTitle>
            <AppText>Ingresa con tus credenciales para continuar.</AppText>
          </div>

          <form class="space-y-5">
            <AppInput
              id="email"
              type="email"
              label="Correo electrónico"
              placeholder="nombre@empresa.com"
            />

            <div class="space-y-2">
              <div class="flex items-center justify-between gap-4">
                <label for="password" class="text-sm font-medium text-[var(--app-color-label)]">
                  Contraseña
                </label>
                <AppLink :to="{ name: 'auth.password-recovery' }" variant="subtle" class="text-sm">
                  ¿Olvidaste tu contraseña?
                </AppLink>
              </div>
              <AppInput id="password" type="password" placeholder="Ingresa tu contraseña" />
            </div>

            <AppButton type="submit" variant="primary" full-width>Entrar</AppButton>
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
