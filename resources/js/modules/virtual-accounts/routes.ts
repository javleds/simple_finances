import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    name: 'admin.virtual-accounts',
    path: 'virtual-accounts',
    component: () => import('./pages/VirtualAccountsPage.vue'),
  },
];

export default routes;
