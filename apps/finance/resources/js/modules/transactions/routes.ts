import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    name: 'admin.transactions',
    path: '/transactions',
    component: () => import('./pages/TransactionsPage.vue'),
  },
];

export default routes;
