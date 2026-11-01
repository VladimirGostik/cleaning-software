process.env.TZ = 'UTC';

import { defineConfig } from 'vitest/config';
import { fileURLToPath, URL } from 'node:url';
import vue from '@vitejs/plugin-vue';
import Icons from 'unplugin-icons/vite';

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
        Icons({ compiler: 'vue3', scale: 1 }),
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
