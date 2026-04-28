import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    name: 'admin.accounts',
    path: 'accounts',
    component: () => import('./pages/AccountsPage.vue'),
  },
];

export default routes;
