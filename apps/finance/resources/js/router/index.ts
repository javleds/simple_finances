import { createRouter, createWebHistory } from 'vue-router';

import { getStoredAuthToken } from '@/lib/api/apiClient';
import { getPendingVerificationEmail, getStoredAuthSession } from '@/modules/auth/lib/authSession';
import { resolvePostAuthAction } from '@/modules/auth/lib/postAuthRedirect';
import { getLegalAcceptance } from '@/modules/auth/legal/legalDocuments';
import authRoutes from '@/modules/auth/routes';
import adminRoutes from '@/modules/admin/routes';

const router = createRouter({
  history: createWebHistory('/'),
  routes: [
    {
      path: '/',
      redirect: {
        name: 'auth.login',
      },
    },
    ...authRoutes,
    ...adminRoutes,
    {
      name: 'not-found',
      path: '/:pathMatch(.*)*',
      component: () => import('@/pages/NotFoundPage.vue'),
    },
  ],
});

router.beforeEach(async (to) => {
  const routeName = typeof to.name === 'string' ? to.name : '';
  const isLegalRoute = [
    'auth.terms',
    'auth.privacy',
    'auth.password-recovery',
    'auth.password-reset',
  ].includes(routeName);
  const isAcceptanceRoute = routeName === 'auth.legal-acceptance';
  if (isLegalRoute) return true;
  const isAdminRoute = routeName.startsWith('admin.');
  const isAuthRoute = routeName.startsWith('auth.');
  const isVerificationRoute = routeName === 'auth.email-verification-required';
  const isEntryAuthRoute = routeName === 'auth.login' || routeName === 'auth.register';
  const hasToken = Boolean(getStoredAuthToken());
  const storedSession = getStoredAuthSession();
  const isVerifiedUser = storedSession?.user.isEmailVerified === true;
  const hasPendingVerificationEmail = Boolean(getPendingVerificationEmail());
  const postAuthAction = resolvePostAuthAction(to.query.post_auth_action);

  if (isAcceptanceRoute && !hasToken) return { name: 'auth.login' };

  if (isAdminRoute && !hasToken) {
    return { name: 'auth.login' };
  }

  if (isAdminRoute && hasToken && !isVerifiedUser) {
    return { name: 'auth.email-verification-required' };
  }

  if (isAdminRoute && hasToken) {
    try {
      const state = await getLegalAcceptance();
      if (state.required || !state.available) {
        return { name: 'auth.legal-acceptance', query: { redirect: to.fullPath } };
      }
    } catch {
      return { name: 'auth.legal-acceptance', query: { redirect: to.fullPath } };
    }
  }

  if (isEntryAuthRoute && hasToken && isVerifiedUser && postAuthAction === 'account-invites') {
    return { name: 'admin.invitations' };
  }

  if (isAuthRoute && hasToken && isVerifiedUser && !isVerificationRoute && !isAcceptanceRoute) {
    return { name: 'admin.dashboard' };
  }

  if (isEntryAuthRoute && !hasToken && hasPendingVerificationEmail && !isVerificationRoute) {
    return { name: 'auth.email-verification-required' };
  }

  return true;
});

export default router;
