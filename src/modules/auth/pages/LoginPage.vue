<script setup lang="ts">
import { useThemeStore } from '@/stores/theme';

const themeStore = useThemeStore();
</script>

<template>
  <section
    class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-100 px-4 py-10 text-slate-950 sm:px-6 lg:px-8 dark:bg-slate-950 dark:text-slate-50"
  >
    <div class="w-full max-w-md">
      <div
        class="rounded-[28px] border border-slate-200/80 bg-white p-6 shadow-[0_24px_80px_-32px_rgba(15,23,42,0.35)] sm:p-8 dark:border-slate-800 dark:bg-slate-900"
      >
        <div class="space-y-6">
          <div
            class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/60"
          >
            <div>
              <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Tema visual</p>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Cambia entre light y dark mode.
              </p>
            </div>

            <div
              class="inline-flex rounded-full border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-900"
            >
              <button
                type="button"
                class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                :class="
                  themeStore.mode === 'light'
                    ? 'bg-slate-950 text-white dark:bg-slate-100 dark:text-slate-950'
                    : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100'
                "
                @click="themeStore.setTheme('light')"
              >
                Light
              </button>
              <button
                type="button"
                class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                :class="
                  themeStore.mode === 'dark'
                    ? 'bg-slate-950 text-white dark:bg-emerald-400 dark:text-slate-950'
                    : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100'
                "
                @click="themeStore.setTheme('dark')"
              >
                Dark
              </button>
            </div>
          </div>

          <div class="space-y-2">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Iniciar sesión</h1>
            <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
              Ingresa con tus credenciales para continuar.
            </p>
          </div>

          <form class="space-y-5">
            <div class="space-y-2">
              <label
                for="email"
                class="text-sm font-medium text-slate-700 dark:text-slate-200"
              >
                Correo electrónico
              </label>
              <input
                id="email"
                type="email"
                placeholder="nombre@empresa.com"
                class="h-12 w-full rounded-2xl border border-slate-300 bg-white px-4 text-sm text-slate-950 outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:placeholder:text-slate-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400/10"
              />
            </div>

            <div class="space-y-2">
              <div class="flex items-center justify-between gap-4">
                <label
                  for="password"
                  class="text-sm font-medium text-slate-700 dark:text-slate-200"
                >
                  Contraseña
                </label>
                <a
                  href=""
                  @click.prevent
                  class="text-sm font-medium text-slate-500 transition hover:text-emerald-700 dark:text-slate-400 dark:hover:text-emerald-300"
                >
                  ¿Olvidaste tu contraseña?
                </a>
              </div>
              <input
                id="password"
                type="password"
                placeholder="Ingresa tu contraseña"
                class="h-12 w-full rounded-2xl border border-slate-300 bg-white px-4 text-sm text-slate-950 outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:placeholder:text-slate-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400/10"
              />
            </div>

            <button
              type="submit"
              class="inline-flex h-12 w-full items-center justify-center rounded-2xl bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-950/10 dark:bg-emerald-400 dark:text-slate-950 dark:hover:bg-emerald-300 dark:focus:ring-emerald-400/20"
            >
              Entrar
            </button>
          </form>

          <div class="border-t border-slate-200 pt-5 dark:border-slate-800">
            <p class="text-sm text-slate-600 dark:text-slate-300">
              ¿Aún no tienes acceso?
              <RouterLink
                :to="{ name: 'auth.register' }"
                class="font-semibold text-slate-950 transition hover:text-emerald-700 dark:text-slate-50 dark:hover:text-emerald-300"
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
