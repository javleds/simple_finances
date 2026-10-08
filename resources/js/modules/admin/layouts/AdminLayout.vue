<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  ArrowLeftIcon,
  ArrowRightOnRectangleIcon,
  BanknotesIcon,
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
  BanknotesIcon as BanknotesSolidIcon,
  CreditCardIcon as CreditCardSolidIcon,
  HomeIcon as HomeSolidIcon,
  Squares2X2Icon as Squares2X2SolidIcon,
  WalletIcon as WalletSolidIcon,
} from '@heroicons/vue/24/solid';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';

import { createAuthRepository } from '@/modules/auth/repositories/authRepository';

type AdminNavigationItem = {
  label: string;
  routeName: string;
  icon: typeof HomeIcon;
  activeIcon: typeof HomeSolidIcon;
};

type ProfileMenuAction = {
  label: string;
  icon: typeof UserIcon;
  actionKey?: string;
  routeName?: string;
};

const route = useRoute();
const router = useRouter();
const activeFacilityName = 'Finanzas Simples';
const isProfileMenuOpen = ref(false);
const profileMenuRef = ref<HTMLElement | null>(null);
const authRepository = createAuthRepository();

const profileMenuActions: ProfileMenuAction[] = [
  {
    label: 'Perfil',
    icon: UserIcon,
    routeName: 'admin.profile',
  },
  {
    label: 'Invitaciones',
    icon: EnvelopeIcon,
    routeName: 'admin.invitations',
  },
  {
    label: 'Salir',
    icon: ArrowRightOnRectangleIcon,
    actionKey: 'logout',
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
    label: 'Ahorro',
    routeName: 'admin.virtual-accounts',
    icon: BanknotesIcon,
    activeIcon: BanknotesSolidIcon,
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

function showBackButton(): boolean {
  if (typeof route.name !== 'string') {
    return false;
  }

  if (route.name === 'admin.distribution.detail') {
    return true;
  }

  if (route.name === 'admin.profile' || route.name === 'admin.invitations') {
    return true;
  }

  if (route.name === 'admin.transactions' || route.name === 'admin.virtual-accounts') {
    return true;
  }

  return route.name.startsWith('admin.accounts.') && route.name !== 'admin.accounts';
}

function isActiveRoute(routeName: string): boolean {
  if (typeof route.name !== 'string') {
    return false;
  }

  if (route.name === routeName) {
    return true;
  }

  return route.name.startsWith(`${routeName}.`);
}

function toggleProfileMenu(): void {
  isProfileMenuOpen.value = !isProfileMenuOpen.value;
}

function closeProfileMenu(): void {
  isProfileMenuOpen.value = false;
}

function handleProfileMenuAction(): void {
  closeProfileMenu();
}

async function handleLogout(): Promise<void> {
  closeProfileMenu();
  await authRepository.logout();
  await router.push({ name: 'auth.login' });
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
    class="relative min-h-screen overflow-hidden bg-(--app-color-page) px-3 pt-4 pb-28 text-(--app-color-text) sm:px-6 sm:pt-8 sm:pb-32"
  >
    <div
      class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_var(--app-color-page-glow),_transparent_52%)]"
    />

    <header
      class="fixed top-0 left-1/2 z-20 w-full max-w-[430px] -translate-x-1/2 border-b bg-[color-mix(in_srgb,var(--app-color-surface)_94%,transparent)] px-5 pt-5 pb-4 backdrop-blur"
      :style="{ borderColor: 'var(--app-color-border)' }"
    >
      <div class="grid grid-cols-[2.75rem_minmax(0,1fr)_2.75rem] items-center gap-3">
        <div class="flex justify-start">
          <RouterLink
            v-if="showBackButton()"
            :to="
              route.name === 'admin.distribution.detail'
                ? { name: 'admin.distribution' }
                : route.name === 'admin.profile' ||
                    route.name === 'admin.invitations' ||
                    route.name === 'admin.transactions' ||
                    route.name === 'admin.virtual-accounts'
                  ? { name: 'admin.dashboard' }
                  : { name: 'admin.accounts' }
            "
            class="flex h-11 w-11 items-center justify-center rounded-full border bg-(--app-color-surface-muted) text-(--app-color-text) transition hover:bg-(--app-color-surface) focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
            :style="{ borderColor: 'var(--app-color-border)' }"
            :aria-label="
              route.name === 'admin.distribution.detail'
                ? 'Volver a distribución'
                : route.name === 'admin.profile' ||
                    route.name === 'admin.invitations' ||
                    route.name === 'admin.transactions' ||
                    route.name === 'admin.virtual-accounts'
                  ? 'Volver al escritorio'
                  : 'Volver a cuentas'
            "
          >
            <ArrowLeftIcon class="h-5 w-5" />
          </RouterLink>
        </div>

        <div class="min-w-0">
          <h1
            class="truncate text-left text-lg font-semibold tracking-tight text-(--app-color-text)"
          >
            {{ activeFacilityName }}
          </h1>
        </div>

        <div ref="profileMenuRef" class="relative flex justify-end">
          <button
            type="button"
            aria-label="Perfil de usuario"
            class="flex h-11 w-11 items-center justify-center rounded-full border bg-(--app-color-surface-muted) text-(--app-color-text) transition hover:bg-(--app-color-surface) focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click.stop="toggleProfileMenu"
          >
            <UserCircleIcon class="h-7 w-7" />
          </button>

          <div
            v-if="isProfileMenuOpen"
            class="absolute top-[calc(100%+0.75rem)] right-0 w-52 rounded-2xl border bg-(--app-color-surface) p-2 shadow-(--app-shadow-card)"
            :style="{ borderColor: 'var(--app-color-border-strong)' }"
          >
            <div class="space-y-1">
              <component
                :is="action.routeName ? RouterLink : 'button'"
                v-for="action in profileMenuActions"
                :key="action.label"
                :to="action.routeName ? { name: action.routeName } : undefined"
                type="button"
                class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-medium text-(--app-color-text) transition hover:bg-(--app-color-surface-muted) focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
                @click="action.actionKey === 'logout' ? handleLogout() : handleProfileMenuAction()"
              >
                <component :is="action.icon" class="h-5 w-5 text-(--app-color-text-subtle)" />
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
        class="flex w-full flex-col overflow-hidden rounded-[32px] border bg-(--app-color-surface) shadow-(--app-shadow-card)"
        :style="{ borderColor: 'var(--app-color-border-strong)' }"
      >
        <main class="flex-1 overflow-y-auto px-5 pt-5 pb-8">
          <RouterView />
        </main>
      </div>
    </div>

    <nav
      class="fixed bottom-0 left-1/2 z-20 w-full max-w-[430px] -translate-x-1/2 border-x border-t bg-[color-mix(in_srgb,var(--app-color-surface)_92%,transparent)] px-2 pt-1 pb-2 backdrop-blur"
      :style="{ borderColor: 'var(--app-color-border-strong)' }"
      aria-label="Primary"
    >
      <ul class="grid grid-cols-6 gap-1">
        <li v-for="item in navigationItems" :key="item.routeName">
          <RouterLink
            :to="{ name: item.routeName }"
            class="flex min-h-16 flex-col items-center justify-center gap-1 rounded-2xl px-1 py-2 text-center transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
            :class="
              isActiveRoute(item.routeName)
                ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                : 'text-(--app-color-text-subtle) hover:bg-(--app-color-surface-muted) hover:text-(--app-color-text)'
            "
          >
            <component
              :is="isActiveRoute(item.routeName) ? item.activeIcon : item.icon"
              class="h-5 w-5 shrink-0"
            />
            <span class="text-[11px] leading-4 font-medium">{{ item.label }}</span>
          </RouterLink>
        </li>
      </ul>
    </nav>
  </section>
</template>
