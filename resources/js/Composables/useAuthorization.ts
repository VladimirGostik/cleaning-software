import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useCapabilitiesStore } from '@/stores/capabilities';

/**
 * useAuthorization — declarative capability checks backed by the Pinia
 * capabilities store.
 *
 * Returns plain functions (not reactive refs). They remain reactive in
 * templates because they read Pinia getter values which are reactive.
 *
 * Usage:
 *   const { can, hasFeature, allows, canCreateTenant } = useAuthorization();
 *   can('view clients')          → true if user has the permission
 *   hasFeature('clients')        → true if tenant plan includes the feature
 *   allows('view clients', 'clients') → AND of both checks
 *   canCreateTenant              → true if plan allows more tenants (null = unlimited)
 */
export function useAuthorization() {
    const store = useCapabilitiesStore();
    const { remainingTenantSlots } = storeToRefs(store);

    function can(permission: App.Enums.PermissionEnum): boolean {
        return store.hasPermission(permission);
    }

    function hasFeature(feature: App.Enums.FeatureEnum): boolean {
        return store.hasFeatureFlag(feature);
    }

    function allows(permission: App.Enums.PermissionEnum, feature: App.Enums.FeatureEnum): boolean {
        return can(permission) && hasFeature(feature);
    }

    const canCreateTenant = computed(
        () => remainingTenantSlots.value === null || remainingTenantSlots.value > 0,
    );

    return { can, hasFeature, allows, canCreateTenant };
}
