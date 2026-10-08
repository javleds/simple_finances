import { fileURLToPath } from 'node:url';
import { mergeConfig, defineConfig, configDefaults } from 'vitest/config';
import viteConfig from './vite.shared';

export default mergeConfig(
    viteConfig,
    defineConfig({
        test: {
            include: ['resources/js/**/*.{test,spec}.{js,ts}'],
            environment: 'jsdom',
            exclude: [...configDefaults.exclude, 'e2e/**'],
            root: fileURLToPath(new URL('./', import.meta.url)),
        },
    }),
);
