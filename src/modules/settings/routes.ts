import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    name: 'admin.settings',
    path: 'settings',
    component: () => import('./pages/ConfigPage.vue'),
  },
];

export default routes;
