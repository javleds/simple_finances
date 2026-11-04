import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    name: 'admin.settings',
    path: 'settings',
    component: () => import('./pages/ConfigPage.vue'),
  },
  {
    name: 'admin.settings.utilities.credit-card-payoff',
    path: 'settings/utilities/credit-card-payoff',
    component: () => import('./pages/CreditCardPayoffPage.vue'),
  },
];

export default routes;
