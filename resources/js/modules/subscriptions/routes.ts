import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    name: 'admin.subscriptions',
    path: 'subscriptions',
    component: () => import('./pages/SubscriptionsPage.vue'),
  },
];

export default routes;
