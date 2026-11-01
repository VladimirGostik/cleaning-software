process.env.TZ = 'UTC';

import { defineConfig } from 'vitest/config';
import { fileURLToPath, URL } from 'node:url';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'happy-dom',
        environmentOptions: {
            happyDOM: {
                url: 'http://localhost',
            },
        },
        env: {
            TZ: 'UTC',
        },
        setupFiles: ['resources/js/vitest.setup.ts'],
        include: ['resources/js/**/*.spec.ts'],
        globals: false,
    },
});
