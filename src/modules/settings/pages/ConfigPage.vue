<script setup lang="ts">
import { reactive } from 'vue';

import { accounts } from '@/modules/accounts/data/accounts';
import { THEME_MODE, useThemeStore, type ThemeMode } from '@/stores/theme';
import { AppCard, AppText, AppTitle, AppToggleButton } from '@/modules/shared/components';

type NotificationSetting = {
  id: string;
  title: string;
  description: string;
  enabled: boolean;
};

type AccountNotificationSetting = {
  id: string;
  accountName: string;
  enabled: boolean;
};

const themeStore = useThemeStore();

const themeOptions = [
  { value: THEME_MODE.LIGHT, label: 'Light' },
  { value: THEME_MODE.DARK, label: 'Dark' },
] as const;

const globalNotificationSettings = reactive<NotificationSetting[]>([
  {
    id: 'payment-reminders',
    title: 'Recordatorios de pago',
    description: 'Avisa cuando una cuenta esté cerca de su fecha de corte o pago programado.',
    enabled: true,
  },
  {
    id: 'balance-alerts',
    title: 'Alertas de saldo',
    description: 'Notifica cuando una cuenta baje de su umbral operativo o requiera fondeo.',
    enabled: true,
  },
  {
    id: 'member-activity',
    title: 'Actividad de colaboradores',
    description:
      'Envía avisos cuando un usuario realice movimientos relevantes o cambios de estado.',
    enabled: false,
  },
]);

const accountNotificationSettings = reactive<AccountNotificationSetting[]>(
  accounts.map((account, index) => ({
    id: account.id,
    accountName: account.name,
    enabled: index < 3,
  })),
);

function updateTheme(nextTheme: string): void {
  themeStore.setTheme(nextTheme as ThemeMode);
}

function toggleGlobalSetting(settingId: string): void {
  const setting = globalNotificationSettings.find((item) => item.id === settingId);

  if (!setting) {
    return;
  }

  setting.enabled = !setting.enabled;
}

function toggleAccountSetting(accountId: string): void {
  const accountSetting = accountNotificationSettings.find((item) => item.id === accountId);

  if (!accountSetting) {
    return;
  }

  accountSetting.enabled = !accountSetting.enabled;
}
</script>

<template>
  <div class="space-y-5">
    <AppCard class="rounded-3xl">
      <div class="flex items-center justify-between gap-4">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Tema visual</AppTitle>
          <AppText>Cambia entre light y dark mode para toda la experiencia administrativa.</AppText>
        </div>

        <AppToggleButton
          :model-value="themeStore.mode"
          :options="themeOptions"
          @update:model-value="updateTheme"
        />
      </div>
    </AppCard>

    <section class="space-y-3">
      <div class="space-y-1">
        <AppTitle as="h2" size="sm">Configuración de notificaciones</AppTitle>
        <AppText
          >Controla qué avisos globales de cuenta se mantienen activos para la facility.</AppText
        >
      </div>

      <div class="space-y-4">
        <AppCard
          v-for="setting in globalNotificationSettings"
          :key="setting.id"
          class="rounded-xl p-3.5! shadow-none transition hover:border-(--app-color-border-strong)"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 space-y-1">
              <p class="text-sm font-semibold text-(--app-color-text)">
                {{ setting.title }}
              </p>
              <AppText size="sm">{{ setting.description }}</AppText>
            </div>

            <button
              type="button"
              class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
              :class="
                setting.enabled ? 'bg-(--app-color-primary)' : 'bg-(--app-color-border-strong)'
              "
              :aria-pressed="setting.enabled"
              @click="toggleGlobalSetting(setting.id)"
            >
              <span
                class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition"
                :class="setting.enabled ? 'translate-x-6' : 'translate-x-1'"
              />
            </button>
          </div>
        </AppCard>
      </div>
    </section>

    <section class="space-y-3">
      <div class="space-y-1">
        <AppTitle as="h2" size="sm">Notificación por cuentas</AppTitle>
        <AppText>Activa o apaga avisos individuales según la cuenta que quieras seguir.</AppText>
      </div>

      <div class="space-y-4">
        <AppCard
          v-for="setting in accountNotificationSettings"
          :key="setting.id"
          class="rounded-xl p-3.5! shadow-none transition hover:border-(--app-color-border-strong)"
        >
          <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
              <p
                class="[display:-webkit-box] overflow-hidden text-sm leading-5 font-semibold text-(--app-color-text) [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
              >
                {{ setting.accountName }}
              </p>
            </div>

            <button
              type="button"
              class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
              :class="
                setting.enabled ? 'bg-(--app-color-primary)' : 'bg-(--app-color-border-strong)'
              "
              :aria-pressed="setting.enabled"
              @click="toggleAccountSetting(setting.id)"
            >
              <span
                class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition"
                :class="setting.enabled ? 'translate-x-6' : 'translate-x-1'"
              />
            </button>
          </div>
        </AppCard>
      </div>
    </section>
  </div>
</template>
