import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    path: '/admin',
    component: () => import('./layouts/AdminLayout.vue'),
    children: [
      {
        name: 'admin.dashboard',
        path: 'dashboard',
        component: () => import('./pages/DashboardPage.vue'),
      },
    ],
  },
];

export default routes;
