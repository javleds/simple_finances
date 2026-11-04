import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
    {
        path: 'personal-money',
        name: 'admin.personal-money',
        component: () => import('./pages/PersonalMoneyPage.vue'),
    },
];

export default routes;
