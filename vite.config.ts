import { defineConfig, mergeConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vueDevTools from 'vite-plugin-vue-devtools';
import sharedConfig from './vite.shared';

export default mergeConfig(
  sharedConfig,
  defineConfig({
    plugins: [
      laravel({
        input: ['resources/js/main.ts'],
        refresh: ['resources/views/**', 'routes/**'],
      }),
      vueDevTools(),
    ],
    server: {
      host: '127.0.0.1',
      port: 5173,
      strictPort: true,
    },
  }),
);
