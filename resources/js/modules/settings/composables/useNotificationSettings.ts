import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed, ref } from 'vue';

import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';
import { notificationSettingsQueryKeys } from '@/modules/settings/queries/notificationSettingsQueries';
import {
  createNotificationSettingsRepository,
  type AccountNotificationSetting,
  type NotificationSettingsState,
  type NotificationTypeSetting,
} from '@/modules/settings/repositories/notificationSettingsRepository';

const notificationSettingsRepository = createNotificationSettingsRepository();
const emptySettings: NotificationSettingsState = {
  notificationTypes: [],
  accounts: [],
};

function toggleNotificationType(
  settings: NotificationSettingsState,
  settingId: string,
): NotificationSettingsState {
  return {
    ...settings,
    notificationTypes: settings.notificationTypes.map((setting) =>
      setting.id === settingId ? { ...setting, enabled: !setting.enabled } : setting,
    ),
  };
}

function toggleAccountNotification(
  settings: NotificationSettingsState,
  accountId: string,
): NotificationSettingsState {
  return {
    ...settings,
    accounts: settings.accounts.map((account) =>
      account.id === accountId ? { ...account, enabled: !account.enabled } : account,
    ),
  };
}

export function useNotificationSettings() {
  const queryClient = useQueryClient();
  const saveError = ref<string | null>(null);
  const isManualLoading = ref(false);

  const settingsQuery = useQuery({
    queryKey: notificationSettingsQueryKeys.detail(),
    queryFn: () => notificationSettingsRepository.get(),
  });

  const updateSettingsMutation = useMutation({
    mutationFn: (settings: NotificationSettingsState) =>
      notificationSettingsRepository.update(settings),
  });

  const settings = computed(() => settingsQuery.data.value ?? emptySettings);
  const globalNotificationSettings = computed<NotificationTypeSetting[]>(
    () => settings.value.notificationTypes,
  );
  const accountNotificationSettings = computed<AccountNotificationSetting[]>(
    () => settings.value.accounts,
  );
  const isLoading = computed(() => isManualLoading.value || settingsQuery.isLoading.value);

  async function loadSettings(): Promise<void> {
    isManualLoading.value = true;
    saveError.value = null;

    try {
      const result = await settingsQuery.refetch();

      if (result.error) {
        saveError.value = resolveApiErrorMessage(
          result.error,
          'No fue posible cargar la configuración.',
        );
      }
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible cargar la configuración.');
    } finally {
      isManualLoading.value = false;
    }
  }

  async function persistSettings(nextSettings: NotificationSettingsState): Promise<void> {
    saveError.value = null;
    queryClient.setQueryData(notificationSettingsQueryKeys.detail(), nextSettings);

    try {
      await updateSettingsMutation.mutateAsync(nextSettings);
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible guardar la configuración.');
      await loadSettings();
    }
  }

  async function toggleGlobalSetting(settingId: string): Promise<void> {
    const exists = settings.value.notificationTypes.some((setting) => setting.id === settingId);

    if (!exists) {
      return;
    }

    await persistSettings(toggleNotificationType(settings.value, settingId));
  }

  async function toggleAccountSetting(accountId: string): Promise<void> {
    const exists = settings.value.accounts.some((account) => account.id === accountId);

    if (!exists) {
      return;
    }

    await persistSettings(toggleAccountNotification(settings.value, accountId));
  }

  return {
    globalNotificationSettings,
    accountNotificationSettings,
    isLoading,
    saveError,
    loadSettings,
    toggleGlobalSetting,
    toggleAccountSetting,
  };
}
