import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    path: '/auth',
    component: () => import('./layouts/AuthLayout.vue'),
    children: [
      {
        name: 'auth.login',
        path: '',
        component: () => import('./pages/LoginPage.vue'),
      },
      {
        name: 'auth.register',
        path: 'register',
        component: () => import('./pages/RegisterPage.vue'),
      },
      {
        name: 'auth.password-recovery',
        path: 'password-recovery',
        component: () => import('./pages/PasswordRecovery.vue'),
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
