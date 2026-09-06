import { ref, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { usePageProps } from '@/Composables/usePageProps';

export interface TenantSwitchVisit {
    /** Tenant id whose switch is in flight; null when idle. */
    switchingTo: Ref<string | null>;
    /** Switches to `tenantId` when it is not the active tenant, then lands on `url`. */
    navigate: (tenantId: string, url: string) => void;
}

/**
 * Deviation from plan step 1 (Q1 orchestrator override): the BE `POST /tenants/{id}/switch`
 * accepts `redirect_to` (relative path) and redirects the response there directly, so no
 * client-side follow-up `router.visit` is needed after the switch (ADR-F2 double-navigation
 * default superseded).
 */
export function useTenantSwitchVisit(): TenantSwitchVisit {
    const props = usePageProps();
    const switchingTo = ref<string | null>(null);

    function clear(): void {
        switchingTo.value = null;
    }

    function navigate(tenantId: string, url: string): void {
        const isCurrent = props.value.tenant.active?.id === tenantId;

        // Alert URLs from the BE may be absolute (`http://host/...`); TenantSwitchData.redirect_to
        // only accepts relative paths, and same-origin router.visit should stay relative too.
        // Foreign origins fall back to the dashboard (defence in depth — BE is also moving to
        // emit relative paths only).
        const target = new URL(url, window.location.origin);
        const relative = target.origin === window.location.origin ? target.pathname + target.search + target.hash : '/';

        if (isCurrent) {
            if (relative === '/') {
                router.reload({ only: ['overview'] });
                return;
            }
            router.visit(relative);
            return;
        }

        if (switchingTo.value !== null) return;

        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }

        router.post(
            `/tenants/${tenantId}/switch`,
            { redirect_to: relative },
            {
                preserveState: false,
                onStart: () => (switchingTo.value = tenantId),
                onError: clear,
                onCancel: clear,
                onFinish: clear,
            },
        );
    }

    return { switchingTo, navigate };
}
