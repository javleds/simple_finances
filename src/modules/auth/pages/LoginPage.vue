<script setup lang="ts">
import { computed } from 'vue';

import { THEME_MODE, useThemeStore } from '@/stores/theme';

type LoginPaletteTone = {
  pageBg: string;
  pageGlow: string;
  cardBg: string;
  cardBorder: string;
  cardShadow: string;
  panelBg: string;
  panelBorder: string;
  heading: string;
  body: string;
  muted: string;
  label: string;
  inputBg: string;
  inputBorder: string;
  inputText: string;
  placeholder: string;
  focusBorder: string;
  focusRing: string;
  buttonBg: string;
  buttonHover: string;
  buttonText: string;
  link: string;
  linkHover: string;
  toggleSurface: string;
  toggleBorder: string;
  toggleActiveBg: string;
  toggleActiveText: string;
  toggleIdleText: string;
  toggleIdleHover: string;
};

const lightPalette: LoginPaletteTone = {
  pageBg: '#f5f7fb',
  pageGlow: 'rgba(29, 78, 216, 0.10)',
  cardBg: '#ffffff',
  cardBorder: '#dbe4f0',
  cardShadow: '0 24px 80px -32px rgba(15, 23, 42, 0.22)',
  panelBg: '#f7faff',
  panelBorder: '#d8e3f2',
  heading: '#0f172a',
  body: '#475569',
  muted: '#64748b',
  label: '#1e293b',
  inputBg: '#ffffff',
  inputBorder: '#cbd5e1',
  inputText: '#0f172a',
  placeholder: '#94a3b8',
  focusBorder: '#1d4ed8',
  focusRing: 'rgba(29, 78, 216, 0.12)',
  buttonBg: '#1d4ed8',
  buttonHover: '#1e40af',
  buttonText: '#eff6ff',
  link: '#1d4ed8',
  linkHover: '#1e3a8a',
  toggleSurface: '#ffffff',
  toggleBorder: '#dbe4f0',
  toggleActiveBg: '#0f172a',
  toggleActiveText: '#ffffff',
  toggleIdleText: '#64748b',
  toggleIdleHover: '#0f172a',
};

const darkPalette: LoginPaletteTone = {
  pageBg: '#101215',
  pageGlow: 'rgba(59, 130, 246, 0.03)',
  cardBg: '#171a20',
  cardBorder: '#2c313b',
  cardShadow: '0 32px 90px -42px rgba(0, 0, 0, 0.72)',
  panelBg: '#1f232b',
  panelBorder: '#353b47',
  heading: '#f3f6fb',
  body: '#c2cad8',
  muted: '#8b95a7',
  label: '#e4e9f2',
  inputBg: '#12151b',
  inputBorder: '#374050',
  inputText: '#f8fbff',
  placeholder: '#768194',
  focusBorder: '#2563eb',
  focusRing: 'rgba(37, 99, 235, 0.16)',
  buttonBg: '#1e40af',
  buttonHover: '#1d4ed8',
  buttonText: '#eff6ff',
  link: '#93c5fd',
  linkHover: '#bfdbfe',
  toggleSurface: '#12151b',
  toggleBorder: '#353b47',
  toggleActiveBg: '#dbe4ff',
  toggleActiveText: '#101215',
  toggleIdleText: '#8b95a7',
  toggleIdleHover: '#f3f6fb',
};

const themeStore = useThemeStore();

const activePaletteTone = computed(() => {
  return themeStore.mode === THEME_MODE.DARK ? darkPalette : lightPalette;
});

const loginPaletteStyle = computed<Record<string, string>>(() => {
  const tone = activePaletteTone.value;

  return {
    '--login-page-bg': tone.pageBg,
    '--login-page-glow': tone.pageGlow,
    '--login-card-bg': tone.cardBg,
    '--login-card-border': tone.cardBorder,
    '--login-card-shadow': tone.cardShadow,
    '--login-panel-bg': tone.panelBg,
    '--login-panel-border': tone.panelBorder,
    '--login-heading': tone.heading,
    '--login-body': tone.body,
    '--login-muted': tone.muted,
    '--login-label': tone.label,
    '--login-input-bg': tone.inputBg,
    '--login-input-border': tone.inputBorder,
    '--login-input-text': tone.inputText,
    '--login-placeholder': tone.placeholder,
    '--login-focus-border': tone.focusBorder,
    '--login-focus-ring': tone.focusRing,
    '--login-button-bg': tone.buttonBg,
    '--login-button-hover': tone.buttonHover,
    '--login-button-text': tone.buttonText,
    '--login-link': tone.link,
    '--login-link-hover': tone.linkHover,
    '--login-toggle-surface': tone.toggleSurface,
    '--login-toggle-border': tone.toggleBorder,
    '--login-toggle-active-bg': tone.toggleActiveBg,
    '--login-toggle-active-text': tone.toggleActiveText,
    '--login-toggle-idle-text': tone.toggleIdleText,
    '--login-toggle-idle-hover': tone.toggleIdleHover,
  };
});
</script>

<template>
  <section
    :style="loginPaletteStyle"
    class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[var(--login-page-bg)] px-4 py-10 text-[var(--login-heading)] sm:px-6 lg:px-8"
  >
    <div
      class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_var(--login-page-glow),_transparent_48%)]"
    />

    <div class="w-full max-w-md">
      <div
        class="rounded-[28px] border bg-[var(--login-card-bg)] p-6 shadow-[var(--login-card-shadow)] sm:p-8"
        :style="{ borderColor: 'var(--login-card-border)' }"
      >
        <div class="space-y-6">
          <div
            class="rounded-2xl border bg-[var(--login-panel-bg)] px-4 py-3"
            :style="{ borderColor: 'var(--login-panel-border)' }"
          >
            <div class="space-y-3">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-sm font-medium text-[var(--login-heading)]">Tema visual</p>
                  <p class="text-xs text-[var(--login-muted)]">
                    Cambia entre light y dark mode.
                  </p>
                </div>

                <div
                  class="inline-flex rounded-full border bg-[var(--login-toggle-surface)] p-1"
                  :style="{ borderColor: 'var(--login-toggle-border)' }"
                >
                  <button
                    type="button"
                    class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                      themeStore.mode === THEME_MODE.LIGHT
                        ? 'bg-[var(--login-toggle-active-bg)] text-[var(--login-toggle-active-text)]'
                        : 'text-[var(--login-toggle-idle-text)] hover:text-[var(--login-toggle-idle-hover)]'
                    "
                    @click="themeStore.setTheme(THEME_MODE.LIGHT)"
                  >
                    Light
                  </button>
                  <button
                    type="button"
                    class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                      themeStore.mode === THEME_MODE.DARK
                        ? 'bg-[var(--login-toggle-active-bg)] text-[var(--login-toggle-active-text)]'
                        : 'text-[var(--login-toggle-idle-text)] hover:text-[var(--login-toggle-idle-hover)]'
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
            <h1 class="text-2xl font-semibold tracking-tight text-[var(--login-heading)] sm:text-3xl">
              Iniciar sesión
            </h1>
            <p class="text-sm leading-6 text-[var(--login-body)]">
              Ingresa con tus credenciales para continuar.
            </p>
          </div>

          <form class="space-y-5">
            <div class="space-y-2">
              <label for="email" class="text-sm font-medium text-[var(--login-label)]">
                Correo electrónico
              </label>
              <input
                id="email"
                type="email"
                placeholder="nombre@empresa.com"
                class="h-12 w-full rounded-2xl border border-[var(--login-input-border)] bg-[var(--login-input-bg)] px-4 text-sm text-[var(--login-input-text)] outline-none placeholder:text-[var(--login-placeholder)] transition focus:border-[var(--login-focus-border)] focus:ring-4 focus:ring-[var(--login-focus-ring)]"
              />
            </div>

            <div class="space-y-2">
              <div class="flex items-center justify-between gap-4">
                <label for="password" class="text-sm font-medium text-[var(--login-label)]">
                  Contraseña
                </label>
                <a
                  href=""
                  @click.prevent
                  class="text-sm font-medium text-[var(--login-link)] transition hover:text-[var(--login-link-hover)]"
                >
                  ¿Olvidaste tu contraseña?
                </a>
              </div>
              <input
                id="password"
                type="password"
                placeholder="Ingresa tu contraseña"
                class="h-12 w-full rounded-2xl border border-[var(--login-input-border)] bg-[var(--login-input-bg)] px-4 text-sm text-[var(--login-input-text)] outline-none placeholder:text-[var(--login-placeholder)] transition focus:border-[var(--login-focus-border)] focus:ring-4 focus:ring-[var(--login-focus-ring)]"
              />
            </div>

            <button
              type="submit"
              class="inline-flex h-12 w-full items-center justify-center rounded-2xl bg-[var(--login-button-bg)] px-4 text-sm font-semibold text-[var(--login-button-text)] transition hover:bg-[var(--login-button-hover)] focus:outline-none focus:ring-4 focus:ring-[var(--login-focus-ring)]"
            >
              Entrar
            </button>
          </form>

          <div class="border-t pt-5" :style="{ borderColor: 'var(--login-card-border)' }">
            <p class="text-sm text-[var(--login-body)]">
              ¿Aún no tienes acceso?
              <RouterLink
                :to="{ name: 'auth.register' }"
                class="font-semibold text-[var(--login-link)] transition hover:text-[var(--login-link-hover)]"
              >
                Crear cuenta
              </RouterLink>
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
