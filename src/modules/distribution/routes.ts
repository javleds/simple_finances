import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    name: 'admin.distribution',
    path: 'distribution',
    component: () => import('./pages/DistributionPage.vue'),
  },
  {
    name: 'admin.distribution.detail',
    path: 'distribution/:ruleId',
    component: () => import('./pages/DistributionDetailPage.vue'),
  },
];

export default routes;
