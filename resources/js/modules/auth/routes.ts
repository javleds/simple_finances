import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    path: '/auth',
    component: () => import('./layouts/AuthLayout.vue'),
    children: [
      {
        name: 'auth.login',
        path: '',
        alias: '/login',
        component: () => import('./pages/LoginPage.vue'),
      },
      {
        name: 'auth.register',
        path: 'register',
        alias: '/register',
        component: () => import('./pages/RegisterPage.vue'),
      },
      {
        name: 'auth.password-recovery',
        path: 'password-recovery',
        component: () => import('./pages/PasswordRecovery.vue'),
      },
      {
        name: 'auth.password-reset',
        path: 'password-reset',
        alias: '/password-reset/reset',
        component: () => import('./pages/PasswordReset.vue'),
      },
      {
        name: 'auth.email-verification-required',
        path: 'email-verification-required',
        component: () => import('./pages/EmailVerificationRequiredPage.vue'),
      },
      {
        name: 'auth.terms',
        path: 'terms-and-conditions',
        component: () => import('./pages/TermsAndConditions.vue'),
      },
      {
        name: 'auth.privacy',
        path: 'privacy-policy',
        component: () => import('./pages/PrivacyPolicy.vue'),
      },
    ],
  },
];

export default routes;
