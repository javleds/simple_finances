import { apiRequest } from './apiClient.mjs';

export const staticFacilities = [
  {
    key: 'dashboard',
    label: 'Dashboard',
    path: '/admin/dashboard',
    auth: true,
  },
  {
    key: 'accounts',
    label: 'Accounts',
    path: '/admin/accounts',
    auth: true,
  },
  {
    key: 'subscriptions',
    label: 'Subscriptions',
    path: '/admin/subscriptions',
    auth: true,
  },
  {
    key: 'distribution',
    label: 'Distribution',
    path: '/admin/distribution',
    auth: true,
  },
  {
    key: 'transactions',
    label: 'Transactions facility',
    path: '/transactions',
    auth: true,
  },
  {
    key: 'invitations',
    label: 'Facility invitations',
    path: '/admin/invitations',
    auth: true,
  },
  {
    key: 'profile',
    label: 'Profile',
    path: '/admin/profile',
    auth: true,
  },
  {
    key: 'settings',
    label: 'Settings',
    path: '/admin/settings',
    auth: true,
  },
  {
    key: 'login',
    label: 'Login',
    path: '/login',
    auth: false,
  },
  {
    key: 'register',
    label: 'Register',
    path: '/register',
    auth: false,
  },
  {
    key: 'password-recovery',
    label: 'Password recovery',
    path: '/auth/password-recovery',
    auth: false,
  },
  {
    key: 'password-reset',
    label: 'Password reset',
    path: '/auth/password-reset',
    auth: false,
  },
  {
    key: 'email-verification',
    label: 'Email verification required',
    path: '/auth/email-verification-required',
    auth: false,
  },
  {
    key: 'terms',
    label: 'Terms and conditions',
    path: '/auth/terms-and-conditions',
    auth: false,
  },
  {
    key: 'privacy',
    label: 'Privacy policy',
    path: '/auth/privacy-policy',
    auth: false,
  },
];

export async function resolveFacilities(config, token, options = {}) {
  const facilities = [...staticFacilities];

  if (options.includeDynamic === false) {
    return facilities;
  }

  const accountId = await resolveFirstId(config, token, '/accounts?per_page=1');
  const distributionRuleId = await resolveFirstId(config, token, '/fixed-incomes?per_page=1');

  if (accountId) {
    facilities.push(
      {
        key: 'account-transactions',
        label: 'Account transactions',
        path: `/admin/accounts/${accountId}/transactions`,
        auth: true,
      },
      {
        key: 'account-goals',
        label: 'Account goals',
        path: `/admin/accounts/${accountId}/goals`,
        auth: true,
      },
      {
        key: 'account-invitations',
        label: 'Account invitations',
        path: `/admin/accounts/${accountId}/invitations`,
        auth: true,
      },
      {
        key: 'account-users',
        label: 'Account users',
        path: `/admin/accounts/${accountId}/users`,
        auth: true,
      },
    );
  }

  if (distributionRuleId) {
    facilities.push({
      key: 'distribution-detail',
      label: 'Distribution detail',
      path: `/admin/distribution/${distributionRuleId}`,
      auth: true,
    });
  }

  return facilities;
}

async function resolveFirstId(config, token, pathName) {
  try {
    const payload = await apiRequest(config.apiBaseUrl, pathName, { token });
    const firstItem = Array.isArray(payload?.data) ? payload.data[0] : null;

    return firstItem?.id ? String(firstItem.id) : null;
  } catch (error) {
    console.warn(`Could not resolve dynamic facility from ${pathName}: ${error.message}`);
    return null;
  }
}
