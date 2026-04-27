import { createRouter, createWebHistory } from 'vue-router';

import authRoutes from '@/modules/auth/routes';
import adminRoutes from '@/modules/admin/routes';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [...authRoutes, ...adminRoutes],
});

export default router;
