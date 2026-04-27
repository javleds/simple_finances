<script setup lang="ts">
import { THEME_MODE, useThemeStore } from '@/stores/theme';
import {
  AppButton,
  AppCard,
  AppInput,
  AppLink,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

const themeStore = useThemeStore();
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
          <div
            class="rounded-xl border bg-[var(--app-color-surface-muted)] px-4 py-3"
            :style="{ borderColor: 'var(--app-color-border-strong)' }"
          >
            <div class="space-y-3">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-sm font-medium text-[var(--app-color-text)]">Tema visual</p>
                  <p class="text-xs text-[var(--app-color-text-subtle)]">
                    Cambia entre light y dark mode.
                  </p>
                </div>

                <div
                  class="inline-flex rounded-lg border bg-[var(--app-color-input-bg)] p-1"
                  :style="{ borderColor: 'var(--app-color-border-strong)' }"
                >
                  <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                      themeStore.mode === THEME_MODE.LIGHT
                        ? 'bg-[var(--app-color-secondary)] text-[var(--app-color-secondary-foreground)]'
                        : 'text-[var(--app-color-text-subtle)] hover:text-[var(--app-color-text)]'
                    "
                    @click="themeStore.setTheme(THEME_MODE.LIGHT)"
                  >
                    Light
                  </button>
                  <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                      themeStore.mode === THEME_MODE.DARK
                        ? 'bg-[var(--app-color-secondary)] text-[var(--app-color-secondary-foreground)]'
                        : 'text-[var(--app-color-text-subtle)] hover:text-[var(--app-color-text)]'
                    "
                    @click="themeStore.setTheme(THEME_MODE.DARK)"
                  >
                    Dark
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="space-y-2">
            <AppTitle as="h1" size="md">Iniciar sesión</AppTitle>
            <AppText>Ingresa con tus credenciales para continuar.</AppText>
          </div>

          <form class="space-y-5">
            <AppInput id="email" type="email" label="Correo electrónico" placeholder="nombre@empresa.com" />

            <div class="space-y-2">
              <div class="flex items-center justify-between gap-4">
                <label for="password" class="text-sm font-medium text-[var(--app-color-label)]">
                  Contraseña
                </label>
                <AppLink href="" variant="subtle" class="text-sm" @click.prevent>
                  ¿Olvidaste tu contraseña?
                </AppLink>
              </div>
              <AppInput
                id="password"
                type="password"
                placeholder="Ingresa tu contraseña"
              />
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
