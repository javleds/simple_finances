<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  ArrowRightOnRectangleIcon,
  CreditCardIcon,
  EnvelopeIcon,
  HomeIcon,
  Squares2X2Icon,
  UserIcon,
  UserCircleIcon,
  WalletIcon,
} from '@heroicons/vue/24/outline';
import { onBeforeUnmount, onMounted, ref } from 'vue';
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

type ProfileMenuAction = {
  label: string;
  icon: typeof UserIcon;
  routeName?: string;
};

const route = useRoute();
const themeStore = useThemeStore();
const activeFacilityName = 'Facility Principal';
const isProfileMenuOpen = ref(false);
const profileMenuRef = ref<HTMLElement | null>(null);

const themeOptions = [
  { value: THEME_MODE.LIGHT, label: 'Light' },
  { value: THEME_MODE.DARK, label: 'Dark' },
] as const;

const profileMenuActions: ProfileMenuAction[] = [
  {
    label: 'Perfil',
    icon: UserIcon,
  },
  {
    label: 'Invitaciones',
    icon: EnvelopeIcon,
  },
  {
    label: 'Salir',
    icon: ArrowRightOnRectangleIcon,
    routeName: 'auth.login',
  },
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
    label: 'Distro',
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

function toggleProfileMenu(): void {
  isProfileMenuOpen.value = !isProfileMenuOpen.value;
}

function closeProfileMenu(): void {
  isProfileMenuOpen.value = false;
}

function handleDocumentClick(event: MouseEvent): void {
  const target = event.target;

  if (!(target instanceof Node)) {
    return;
  }

  if (profileMenuRef.value?.contains(target)) {
    return;
  }

  closeProfileMenu();
}

onMounted(() => {
  document.addEventListener('click', handleDocumentClick);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleDocumentClick);
});
</script>

<template>
  <section
    class="relative min-h-screen overflow-hidden bg-[var(--app-color-page)] px-3 pb-28 pt-4 text-[var(--app-color-text)] sm:px-6 sm:pb-32 sm:pt-8"
  >
    <div
      class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_var(--app-color-page-glow),_transparent_52%)]"
    />

    <header
      class="fixed left-1/2 top-0 z-20 w-full max-w-[430px] -translate-x-1/2 border-b bg-[color-mix(in_srgb,var(--app-color-surface)_94%,transparent)] px-5 pb-4 pt-5 backdrop-blur"
      :style="{ borderColor: 'var(--app-color-border)' }"
    >
      <div class="flex items-center justify-between gap-4">
        <div class="min-w-0 space-y-1">
          <h1 class="truncate text-lg font-semibold tracking-tight text-[var(--app-color-text)]">
            {{ activeFacilityName }}
          </h1>
        </div>

        <div ref="profileMenuRef" class="relative shrink-0">
          <button
            type="button"
            aria-label="Perfil de usuario"
            class="flex h-11 w-11 items-center justify-center rounded-full border bg-[var(--app-color-surface-muted)] text-[var(--app-color-text)] transition hover:bg-[var(--app-color-surface)] focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click.stop="toggleProfileMenu"
          >
            <UserCircleIcon class="h-7 w-7" />
          </button>

          <div
            v-if="isProfileMenuOpen"
            class="absolute right-0 top-[calc(100%+0.75rem)] w-52 rounded-2xl border bg-[var(--app-color-surface)] p-2 shadow-[var(--app-shadow-card)]"
            :style="{ borderColor: 'var(--app-color-border-strong)' }"
          >
            <div class="space-y-1">
              <component
                :is="action.routeName ? RouterLink : 'button'"
                v-for="action in profileMenuActions"
                :key="action.label"
                :to="action.routeName ? { name: action.routeName } : undefined"
                type="button"
                class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-medium text-[var(--app-color-text)] transition hover:bg-[var(--app-color-surface-muted)] focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
                @click="closeProfileMenu"
              >
                <component :is="action.icon" class="h-5 w-5 text-[var(--app-color-text-subtle)]" />
                <span>{{ action.label }}</span>
              </component>
            </div>
          </div>
        </div>
      </div>
    </header>

    <div
      class="relative mx-auto flex min-h-[calc(100vh-9rem)] max-w-[430px] pt-[5.5rem] sm:min-h-[820px]"
    >
      <div
        class="flex w-full flex-col overflow-hidden rounded-[32px] border bg-[var(--app-color-surface)] shadow-[var(--app-shadow-card)]"
        :style="{ borderColor: 'var(--app-color-border-strong)' }"
      >
        <header class="border-b px-5 pb-4 pt-5" :style="{ borderColor: 'var(--app-color-border)' }">
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
