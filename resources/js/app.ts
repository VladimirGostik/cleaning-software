import './bootstrap';
import { createApp, h, Transition, type DefineComponent } from 'vue';
import { createInertiaApp, router, usePage } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { useCapabilitiesStore } from '@/stores/capabilities';
import { matchRequirement } from '@/lib/routeRequirements';
import type { SharedProps } from '@/types';

const appName = (import.meta.env.VITE_APP_NAME as string) ?? 'CleanMaster';

const pinia = createPinia();

/**
 * Inertia `before` event — synchronous navigation guard.
 *
 * The `before` result type is `boolean | void` (synchronous only in Inertia v3).
 * Async awaiting is not supported; the store is populated eagerly via the
 * `navigate` hook and read synchronously here.
 *
 * Fail-open: if caps not yet loaded (first navigation before /api/me returns),
 * allow the visit. The navigate hook will populate the store and subsequent
 * guards enforce. Real authorization lives on the BE.
 */
router.on('before', (event) => {
    const store = useCapabilitiesStore(pinia);
    const pageProps = usePage().props as unknown as SharedProps;
    const authed = pageProps.auth?.user != null;

    if (!authed) {
        return true;
    }

    if (!store.loaded) {
        // Not yet loaded — allow and let the navigate hook trigger fetch.
        return true;
    }

    const req = matchRequirement(event.detail.visit.url.pathname);
    if (!req) {
        return true;
    }

    const permissionOk = req.permission === undefined || store.hasPermission(req.permission);
    const featureOk = req.feature === undefined || store.hasFeatureFlag(req.feature);

    if (!permissionOk || !featureOk) {
        router.visit('/dashboard');
        return false;
    }

    return true;
});

/**
 * Inertia `navigate` event — fires after every successful page navigation.
 *
 * Primary init path: first authed navigate populates the store via
 * `ensureLoaded()` (idempotent — subsequent calls are no-ops). Errors are
 * caught and logged; the before guard fails-open on a missing load.
 */
router.on('navigate', (event) => {
    const auth = (event.detail.page.props as unknown as SharedProps).auth;
    if (auth?.user == null) {
        return;
    }

    const store = useCapabilitiesStore(pinia);
    store.ensureLoaded().catch((err: unknown) => {
        console.error('[capabilities] Failed to load /api/me:', err);
    });
});

createInertiaApp({
    title: (title) => (title ? `${title} – ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob<DefineComponent>('./Pages/**/*.vue')),
    setup: ({ el, App, props, plugin }) => {
        createApp({
            render: () => h(Transition, { name: 'page', mode: 'out-in', appear: true }, () => h(App, props)),
        })
            .use(pinia)
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#A16207',
    },
});
