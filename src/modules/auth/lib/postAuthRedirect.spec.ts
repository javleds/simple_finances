import { describe, expect, it } from 'vitest';
import type { Router } from 'vue-router';

import {
  resolvePostAuthAction,
  resolvePostAuthRedirectRoute,
} from './postAuthRedirect';

const router = {
  resolve: () => ({
    href: '/admin/invitations',
  }),
} as unknown as Router;

describe('post auth redirect', () => {
  it('accepts only the account invites post-auth action', () => {
    expect(resolvePostAuthAction('account-invites')).toBe('account-invites');
    expect(resolvePostAuthAction('admin.dashboard')).toBeUndefined();
    expect(resolvePostAuthAction(undefined)).toBeUndefined();
  });

  it('resolves the account invitations route for a valid API redirect', () => {
    expect(
      resolvePostAuthRedirectRoute(
        {
          action: 'account-invites',
          url: `${window.location.origin}/admin/invitations`,
        },
        router,
      ),
    ).toEqual({ name: 'admin.invitations' });
  });

  it('rejects external or mismatched redirect URLs', () => {
    expect(
      resolvePostAuthRedirectRoute(
        {
          action: 'account-invites',
          url: 'https://example.com/admin/invitations',
        },
        router,
      ),
    ).toBeNull();

    expect(
      resolvePostAuthRedirectRoute(
        {
          action: 'account-invites',
          url: `${window.location.origin}/admin/dashboard`,
        },
        router,
      ),
    ).toBeNull();
  });
});
