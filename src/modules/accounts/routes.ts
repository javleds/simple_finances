import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    name: 'admin.accounts',
    path: 'accounts',
    component: () => import('./pages/AccountsPage.vue'),
  },
  {
    name: 'admin.accounts.detail',
    path: 'accounts/:accountId',
    component: () => import('./pages/AccountDetailPage.vue'),
  },
];

export default routes;
