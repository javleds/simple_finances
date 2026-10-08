import { createRouter, createWebHistory } from 'vue-router';

import { getStoredAuthToken } from '@/lib/api/apiClient';
import { getPendingVerificationEmail, getStoredAuthSession } from '@/modules/auth/lib/authSession';
import { resolvePostAuthAction } from '@/modules/auth/lib/postAuthRedirect';
import authRoutes from '@/modules/auth/routes';
import adminRoutes from '@/modules/admin/routes';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
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

router.beforeEach((to) => {
  const routeName = typeof to.name === 'string' ? to.name : '';
  const isAdminRoute = routeName.startsWith('admin.');
  const isAuthRoute = routeName.startsWith('auth.');
  const isVerificationRoute = routeName === 'auth.email-verification-required';
  const isEntryAuthRoute = routeName === 'auth.login' || routeName === 'auth.register';
  const hasToken = Boolean(getStoredAuthToken());
  const storedSession = getStoredAuthSession();
  const isVerifiedUser = storedSession?.user.isEmailVerified === true;
  const hasPendingVerificationEmail = Boolean(getPendingVerificationEmail());
  const postAuthAction = resolvePostAuthAction(to.query.post_auth_action);

  if (isAdminRoute && !hasToken) {
    return { name: 'auth.login' };
  }

  if (isAdminRoute && hasToken && !isVerifiedUser) {
    return { name: 'auth.email-verification-required' };
  }

  if (isEntryAuthRoute && hasToken && isVerifiedUser && postAuthAction === 'account-invites') {
    return { name: 'admin.invitations' };
  }

  if (isAuthRoute && hasToken && isVerifiedUser && !isVerificationRoute) {
    return { name: 'admin.dashboard' };
  }

  if (isEntryAuthRoute && !hasToken && hasPendingVerificationEmail && !isVerificationRoute) {
    return { name: 'auth.email-verification-required' };
  }

  return true;
});

export default router;
