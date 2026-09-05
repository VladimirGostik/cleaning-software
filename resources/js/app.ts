/// <reference types="vite/client" />

import '../css/app.css';

import { createApp, h, type DefineComponent, type Plugin } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createI18n } from 'vue-i18n';

import enApp from '../lang/en/app.json';
import skApp from '../lang/sk/app.json';

const appName = (import.meta.env.VITE_APP_NAME as string | undefined) ?? 'App';

const messages = {
    en: { ...enApp },
    sk: { ...skApp },
};

void createInertiaApp({
    title: (title: string | null) =>
        title ? `${title} - ${appName}` : appName,
    resolve: (name: string) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup: ({
        el,
        App,
        props,
        plugin,
    }: {
        el: HTMLElement;
        App: DefineComponent;
        props: Record<string, unknown>;
        plugin: Plugin;
    }) => {
        const initialPage = props.initialPage as { props: Record<string, unknown> } | undefined;
        const locale = (initialPage?.props?.locale as string | undefined) ?? 'en';

        const i18n = createI18n({
            legacy: false,
            locale,
            fallbackLocale: 'en',
            messages,
        });

        router.on('navigate', (event: Event) => {
            const page = (event as unknown as { detail: { page: { props: Record<string, unknown> } } }).detail.page;
            const newLocale = page?.props?.locale as string | undefined;
            if (newLocale) {
                (i18n.global.locale as unknown as { value: string }).value = newLocale;
            }
        });

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .mount(el);
    },
    progress: { color: '#4f46e5' },
});
