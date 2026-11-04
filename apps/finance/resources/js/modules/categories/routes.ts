import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
    {
        name: 'admin.categories',
        path: 'categories',
        component: () => import('./pages/CategoriesPage.vue'),
    },
];

export default routes;
