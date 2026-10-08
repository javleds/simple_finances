import { describe, expect, it } from 'vitest';

import { notificationSettingsQueryKeys } from './notificationSettingsQueries';

describe('notification settings query keys', () => {
  it('uses a stable key for the settings detail payload', () => {
    expect(notificationSettingsQueryKeys.detail()).toEqual(['notification-settings', 'detail']);
  });
});
