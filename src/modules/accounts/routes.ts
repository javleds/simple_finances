import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    name: 'admin.accounts',
    path: 'accounts',
    component: () => import('./pages/AccountsPage.vue'),
  },
  {
    path: 'accounts/:accountId',
    redirect: (to) => ({
      name: 'admin.accounts.detail',
      params: {
        accountId: to.params.accountId,
        section: 'transactions',
      },
    }),
  },
  {
    name: 'admin.accounts.detail',
    path: 'accounts/:accountId/:section',
    component: () => import('./pages/AccountDetailPage.vue'),
  },
];

export default routes;
