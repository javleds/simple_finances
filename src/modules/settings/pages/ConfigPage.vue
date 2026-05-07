<script setup lang="ts">
import { onMounted, ref } from 'vue';

import { ApiError } from '@/lib/api/apiClient';
import { createNotificationSettingsRepository } from '@/modules/settings/repositories/notificationSettingsRepository';
import { THEME_MODE, useThemeStore, type ThemeMode } from '@/stores/theme';
import {
  AppCard,
  AppSwitch,
  AppText,
  AppTitle,
  AppToggleButton,
} from '@/modules/shared/components';

const themeStore = useThemeStore();
const notificationSettingsRepository = createNotificationSettingsRepository();

const themeOptions = [
  { value: THEME_MODE.LIGHT, label: 'Light' },
  { value: THEME_MODE.DARK, label: 'Dark' },
] as const;

const globalNotificationSettings = ref<
  Array<{ id: string; title: string; description: string; enabled: boolean }>
>([]);
const accountNotificationSettings = ref<Array<{ id: string; accountName: string; enabled: boolean }>>(
  [],
);
const isLoading = ref(false);
const saveError = ref<string | null>(null);

function updateTheme(nextTheme: string): void {
  themeStore.setTheme(nextTheme as ThemeMode);
}

onMounted(() => {
  void loadSettings();
});

function resolveErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

async function loadSettings(): Promise<void> {
  isLoading.value = true;
  saveError.value = null;

  try {
    const settings = await notificationSettingsRepository.get();
    globalNotificationSettings.value = settings.notificationTypes;
    accountNotificationSettings.value = settings.accounts;
  } catch (error) {
    saveError.value = resolveErrorMessage(error, 'No fue posible cargar la configuración.');
  } finally {
    isLoading.value = false;
  }
}

async function persistSettings(): Promise<void> {
  saveError.value = null;

  try {
    await notificationSettingsRepository.update({
      notificationTypes: globalNotificationSettings.value,
      accounts: accountNotificationSettings.value,
    });
  } catch (error) {
    saveError.value = resolveErrorMessage(error, 'No fue posible guardar la configuración.');
    await loadSettings();
  }
}

async function toggleGlobalSetting(settingId: string): Promise<void> {
  const setting = globalNotificationSettings.value.find((item) => item.id === settingId);

  if (!setting) {
    return;
  }

  setting.enabled = !setting.enabled;
  await persistSettings();
}

async function toggleAccountSetting(accountId: string): Promise<void> {
  const accountSetting = accountNotificationSettings.value.find((item) => item.id === accountId);

  if (!accountSetting) {
    return;
  }

  accountSetting.enabled = !accountSetting.enabled;
  await persistSettings();
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

      <div v-if="saveError" class="rounded-2xl border border-(--app-color-danger) px-4 py-3">
        <AppText class="text-(--app-color-danger)!">{{ saveError }}</AppText>
      </div>

      <div v-if="isLoading" class="rounded-2xl border px-4 py-6 text-center">
        <AppText>Cargando configuración...</AppText>
      </div>

      <div v-else class="space-y-4">
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

            <AppSwitch
              :model-value="setting.enabled"
              :aria-label="`Alternar ${setting.title}`"
              @update:model-value="void toggleGlobalSetting(setting.id)"
            />
          </div>
        </AppCard>
      </div>
    </section>

    <section class="space-y-3">
      <div class="space-y-1">
        <AppTitle as="h2" size="sm">Notificación por cuentas</AppTitle>
        <AppText>Activa o apaga avisos individuales según la cuenta que quieras seguir.</AppText>
      </div>

      <div v-if="!isLoading" class="space-y-4">
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

            <AppSwitch
              :model-value="setting.enabled"
              :aria-label="`Alternar notificaciones de ${setting.accountName}`"
              @update:model-value="void toggleAccountSetting(setting.id)"
            />
          </div>
        </AppCard>
      </div>
    </section>
  </div>
</template>
