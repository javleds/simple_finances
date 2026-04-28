import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    name: 'admin.distribution',
    path: 'distribution',
    component: () => import('./pages/DistributionPage.vue'),
  },
];

export default routes;
