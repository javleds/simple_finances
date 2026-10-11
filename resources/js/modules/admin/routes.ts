import type { RouteRecordRaw } from 'vue-router';

import categoriesRoutes from '@/modules/categories/routes';
import accountsRoutes from '@/modules/accounts/routes';
import distributionRoutes from '@/modules/distribution/routes';
import settingsRoutes from '@/modules/settings/routes';
import subscriptionsRoutes from '@/modules/subscriptions/routes';
import transactionsRoutes from '@/modules/transactions/routes';
import virtualAccountsRoutes from '@/modules/virtual-accounts/routes';

const routes: RouteRecordRaw[] = [
  {
    path: '/admin',
    component: () => import('./layouts/AdminLayout.vue'),
    redirect: { name: 'admin.dashboard' },
    children: [
      {
        name: 'admin.dashboard',
        path: 'dashboard',
        component: () => import('./pages/DashboardPage.vue'),
      },
      {
        name: 'admin.invitations',
        path: 'invitations',
        component: () => import('./pages/InvitationsPage.vue'),
      },
      {
        name: 'admin.profile',
        path: 'profile',
        component: () => import('./pages/ProfilePage.vue'),
      },
      ...categoriesRoutes,
      ...accountsRoutes,
      ...virtualAccountsRoutes,
      ...subscriptionsRoutes,
      ...distributionRoutes,
      ...transactionsRoutes,
      ...settingsRoutes,
    ],
  },
];

export default routes;
