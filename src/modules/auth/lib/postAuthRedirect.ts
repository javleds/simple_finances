import type { Router } from 'vue-router';

import type {
  AuthPostAuthAction,
  AuthPostAuthRedirect,
} from '@/modules/auth/schemas/authSchemas';

export const ACCOUNT_INVITES_POST_AUTH_ACTION: AuthPostAuthAction = 'account-invites';

export function resolvePostAuthAction(value: unknown): AuthPostAuthAction | undefined {
  return value === ACCOUNT_INVITES_POST_AUTH_ACTION ? ACCOUNT_INVITES_POST_AUTH_ACTION : undefined;
}

export function resolvePostAuthRedirectRoute(
  redirect: AuthPostAuthRedirect | null,
  router: Router,
): { name: 'admin.invitations' } | null {
  if (!redirect || redirect.action !== ACCOUNT_INVITES_POST_AUTH_ACTION) {
    return null;
  }

  if (!isAccountInvitationsUrl(redirect.url, router)) {
    return null;
  }

  return { name: 'admin.invitations' };
}

function isAccountInvitationsUrl(url: string, router: Router): boolean {
  if (typeof window === 'undefined') {
    return false;
  }

  try {
    const expectedUrl = new URL(
      router.resolve({ name: 'admin.invitations' }).href,
      window.location.origin,
    );
    const receivedUrl = new URL(url, window.location.origin);

    return receivedUrl.origin === expectedUrl.origin && receivedUrl.pathname === expectedUrl.pathname;
  } catch {
    return false;
  }
}
