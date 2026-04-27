import type { RouteRecordRaw } from 'vue-router';

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
        name: 'admin.accounts',
        path: 'accounts',
        component: () => import('./pages/DashboardPage.vue'),
      },
      {
        name: 'admin.subscriptions',
        path: 'subscriptions',
        component: () => import('./pages/DashboardPage.vue'),
      },
      {
        name: 'admin.distribution',
        path: 'distribution',
        component: () => import('./pages/DashboardPage.vue'),
      },
      {
        name: 'admin.settings',
        path: 'settings',
        component: () => import('./pages/DashboardPage.vue'),
      },
    ],
  },
];

export default routes;
