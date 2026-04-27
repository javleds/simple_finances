<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  CreditCardIcon,
  HomeIcon,
  Squares2X2Icon,
  WalletIcon,
} from '@heroicons/vue/24/outline';
import {
  AdjustmentsHorizontalIcon as AdjustmentsHorizontalSolidIcon,
  CreditCardIcon as CreditCardSolidIcon,
  HomeIcon as HomeSolidIcon,
  Squares2X2Icon as Squares2X2SolidIcon,
  WalletIcon as WalletSolidIcon,
} from '@heroicons/vue/24/solid';
import { RouterLink, RouterView, useRoute } from 'vue-router';

import { THEME_MODE, useThemeStore, type ThemeMode } from '@/stores/theme';
import { AppText, AppToggleButton } from '@/modules/shared/components';

type AdminNavigationItem = {
  label: string;
  routeName: string;
  icon: typeof HomeIcon;
  activeIcon: typeof HomeSolidIcon;
};

const route = useRoute();
const themeStore = useThemeStore();

const themeOptions = [
  { value: THEME_MODE.LIGHT, label: 'Light' },
  { value: THEME_MODE.DARK, label: 'Dark' },
] as const;

const navigationItems: AdminNavigationItem[] = [
  {
    label: 'Cuentas',
    routeName: 'admin.accounts',
    icon: WalletIcon,
    activeIcon: WalletSolidIcon,
  },
  {
    label: 'Subs',
    routeName: 'admin.subscriptions',
    icon: CreditCardIcon,
    activeIcon: CreditCardSolidIcon,
  },
  {
    label: 'Escritorio',
    routeName: 'admin.dashboard',
    icon: HomeIcon,
    activeIcon: HomeSolidIcon,
  },
  {
    label: 'Distribución',
    routeName: 'admin.distribution',
    icon: Squares2X2Icon,
    activeIcon: Squares2X2SolidIcon,
  },
  {
    label: 'Config',
    routeName: 'admin.settings',
    icon: AdjustmentsHorizontalIcon,
    activeIcon: AdjustmentsHorizontalSolidIcon,
  },
];

function updateTheme(nextTheme: string): void {
  themeStore.setTheme(nextTheme as ThemeMode);
}

function isActiveRoute(routeName: string): boolean {
  return route.name === routeName;
}
</script>

<template>
  <section
    class="relative min-h-screen overflow-hidden bg-[var(--app-color-page)] px-3 pb-28 pt-4 text-[var(--app-color-text)] sm:px-6 sm:pb-32 sm:pt-8"
  >
    <div
      class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_var(--app-color-page-glow),_transparent_52%)]"
    />

    <div class="relative mx-auto flex min-h-[calc(100vh-9rem)] max-w-[430px] sm:min-h-[820px]">
      <div
        class="flex w-full flex-col overflow-hidden rounded-[32px] border bg-[var(--app-color-surface)] shadow-[var(--app-shadow-card)]"
        :style="{ borderColor: 'var(--app-color-border-strong)' }"
      >
        <header class="border-b px-5 pb-4 pt-5" :style="{ borderColor: 'var(--app-color-border)' }">
          <div class="mb-4 flex items-start justify-between gap-4">
            <div class="space-y-1">
              <AppText size="sm" tone="subtle">Finsi Admin</AppText>
              <h1 class="text-lg font-semibold tracking-tight text-[var(--app-color-text)]">
                Panel principal
              </h1>
            </div>

            <div
              class="rounded-2xl border px-3 py-2 text-right"
              :style="{ borderColor: 'var(--app-color-border)' }"
            >
              <AppText size="sm" tone="subtle">Estado</AppText>
              <p class="text-sm font-semibold text-emerald-500">Operando</p>
            </div>
          </div>

          <div
            class="rounded-2xl border bg-[var(--app-color-surface-muted)] px-4 py-3"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="space-y-1">
                <AppText as="div" tone="muted" class="font-medium text-[var(--app-color-text)]">
                  Tema visual
                </AppText>
                <AppText size="sm" tone="subtle">
                  Mantiene el mismo modo que el flujo de autenticación.
                </AppText>
              </div>

              <AppToggleButton
                :model-value="themeStore.mode"
                :options="themeOptions"
                @update:model-value="updateTheme"
              />
            </div>
          </div>
        </header>

        <main class="flex-1 overflow-y-auto px-5 pb-8 pt-5">
          <RouterView />
        </main>
      </div>
    </div>

    <nav
      class="fixed bottom-0 left-1/2 z-20 w-full max-w-[430px] -translate-x-1/2 rounded-t-[28px] border bg-[color-mix(in_srgb,var(--app-color-surface)_92%,transparent)] p-2 backdrop-blur"
      :style="{ borderColor: 'var(--app-color-border-strong)' }"
      aria-label="Primary"
    >
      <ul class="grid grid-cols-5 gap-1">
        <li v-for="item in navigationItems" :key="item.routeName">
          <RouterLink
            :to="{ name: item.routeName }"
            class="flex min-h-16 flex-col items-center justify-center gap-1 rounded-2xl px-1 py-2 text-center transition focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
            :class="
              isActiveRoute(item.routeName)
                ? 'bg-[var(--app-color-primary)] text-[var(--app-color-primary-foreground)]'
                : 'text-[var(--app-color-text-subtle)] hover:bg-[var(--app-color-surface-muted)] hover:text-[var(--app-color-text)]'
            "
          >
            <component
              :is="isActiveRoute(item.routeName) ? item.activeIcon : item.icon"
              class="h-5 w-5 shrink-0"
            />
            <span class="text-[11px] font-medium leading-4">{{ item.label }}</span>
          </RouterLink>
        </li>
      </ul>
    </nav>
  </section>
</template>
