import { createApiClient } from '@/lib/api/apiClient';

export type NotificationTypeSetting = {
  id: string;
  title: string;
  description: string;
  enabled: boolean;
};

export type AccountNotificationSetting = {
  id: string;
  accountName: string;
  enabled: boolean;
};

export type NotificationSettingsState = {
  notificationTypes: NotificationTypeSetting[];
  accounts: AccountNotificationSetting[];
};

const apiClient = createApiClient();

function asRecord(value: unknown): Record<string, unknown> | null {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    return null;
  }

  return value as Record<string, unknown>;
}

function toStringId(value: unknown, fallback: string): string {
  if (typeof value === 'string' || typeof value === 'number') {
    return String(value);
  }

  return fallback;
}

function toBoolean(value: unknown): boolean {
  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'number') {
    return value === 1;
  }

  if (typeof value === 'string') {
    const normalizedValue = value.trim().toLowerCase();
    return normalizedValue === '1' || normalizedValue === 'true' || normalizedValue === 'yes';
  }

  return false;
}

function parseNotificationTypeItem(item: unknown, index: number): NotificationTypeSetting | null {
  const record = asRecord(item);

  if (!record) {
    return null;
  }

  return {
    id: toStringId(record.id, `notification-type-${index}`),
    title:
      typeof record.name === 'string'
        ? record.name
        : typeof record.title === 'string'
          ? record.title
          : `Configuración ${index + 1}`,
    description:
      typeof record.description === 'string'
        ? record.description
        : typeof record.help_text === 'string'
          ? record.help_text
          : '',
    enabled: toBoolean(
      record.enabled ?? record.active ?? record.selected ?? record.attached ?? record.is_enabled,
    ),
  };
}

function parseAccountItem(item: unknown, index: number): AccountNotificationSetting | null {
  const record = asRecord(item);

  if (!record) {
    return null;
  }

  return {
    id: toStringId(record.id ?? record.account_id, `account-${index}`),
    accountName:
      typeof record.name === 'string'
        ? record.name
        : typeof record.account_name === 'string'
          ? record.account_name
          : typeof record.title === 'string'
            ? record.title
            : `Cuenta ${index + 1}`,
    enabled: toBoolean(
      record.enabled ?? record.active ?? record.selected ?? record.attached ?? record.is_enabled,
    ),
  };
}

export function createNotificationSettingsRepository() {
  return {
    async get(): Promise<NotificationSettingsState> {
      const response = await apiClient.get<unknown>('/notification-settings');
      const payload = asRecord(response);
      const data = asRecord(payload?.data);
      const notificationTypes = Array.isArray(data?.notification_types)
        ? data.notification_types
            .map(parseNotificationTypeItem)
            .filter((item): item is NotificationTypeSetting => item !== null)
        : [];
      const accounts = Array.isArray(data?.accounts)
        ? data.accounts
            .map(parseAccountItem)
            .filter((item): item is AccountNotificationSetting => item !== null)
        : [];

      return {
        notificationTypes,
        accounts,
      };
    },
    async update(payload: NotificationSettingsState): Promise<void> {
      await apiClient.put('/notification-settings', {
        notification_type_ids: payload.notificationTypes
          .filter((item) => item.enabled)
          .map((item) => Number(item.id)),
        account_ids: payload.accounts
          .filter((item) => item.enabled)
          .map((item) => Number(item.id)),
      });
    },
  };
}
