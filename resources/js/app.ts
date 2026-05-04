import './bootstrap';
import { createApp, h, Transition, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const appName = (import.meta.env.VITE_APP_NAME as string) ?? 'CleanMaster';

createInertiaApp({
    title: (title) => (title ? `${title} – ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob<DefineComponent>('./Pages/**/*.vue')),
    setup: ({ el, App, props, plugin }) => {
        createApp({
            render: () =>
                h(Transition, { name: 'page', mode: 'out-in', appear: true }, () => h(App, props)),
        })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#A16207',
    },
});
