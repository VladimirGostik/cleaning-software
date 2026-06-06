import { defineStore } from 'pinia';
import { meService } from '@/services';

/**
 * Capabilities store — single source of truth for the current user's
 * permission strings and plan feature flags.
 *
 * Source: GET /api/me (fetched once per session, cached in `loaded`).
 *
 * NOTE: This is a UX-only layer. Real enforcement lives on the BE
 * (`#[Authorize]` policies + `feature:` middleware). The FE guard and
 * `<Can>` component only hide UI / redirect; they are NOT security boundaries.
 *
 * Fetch/reset call sites:
 *  - `ensureLoaded()` — called by the Inertia navigate hook on first authed nav.
 *  - `fetch()` — call explicitly after role/plan changes (flows not yet built).
 *  - `reset()` — call on logout (AppLayout.vue).
 *
 * Error handling: `fetch()` rejects on network/auth error. The caller (navigate
 * hook) catches and logs; guard fails-open (no caps loaded → guarded routes
 * redirect to /dashboard, /dashboard itself is unrestricted).
 */
export const useCapabilitiesStore = defineStore('capabilities', {
    state: () => ({
        permissions: [] as App.Enums.PermissionEnum[],
        features: [] as App.Enums.FeatureEnum[],
        loaded: false,
    }),

    getters: {
        hasPermission:
            (s) =>
            (p: App.Enums.PermissionEnum): boolean =>
                s.permissions.includes(p),

        hasFeatureFlag:
            (s) =>
            (f: App.Enums.FeatureEnum): boolean =>
                s.features.includes(f),
    },

    actions: {
        /** Fetch /api/me and populate permissions + features. */
        async fetch(): Promise<void> {
            const me = await meService.fetchMe();
            this.permissions = me.permissions as App.Enums.PermissionEnum[];
            this.features = me.features as App.Enums.FeatureEnum[];
            this.loaded = true;
        },

        /** Idempotent: fetch only on first call; subsequent calls are no-ops. */
        async ensureLoaded(): Promise<void> {
            if (!this.loaded) {
                await this.fetch();
            }
        },

        /** Clear on logout — call before or after posting /logout. */
        reset(): void {
            this.$reset();
        },
    },
});
