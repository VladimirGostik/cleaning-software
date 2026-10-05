import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
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
    server: {
        host: '0.0.0.0',
        // Must match the published compose port on BOTH sides: the browser loads assets from
        // the URL laravel-vite-plugin writes into public/hot, so a host-only remap would send it
        // to another local project's dev server.
        port: 5175,
        strictPort: true,
        hmr: { host: 'localhost', clientPort: 5175 },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
