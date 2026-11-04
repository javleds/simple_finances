export const notificationSettingsQueryKeys = {
  all: ['notification-settings'] as const,
  detail() {
    return [...this.all, 'detail'] as const;
  },
};
