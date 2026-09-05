import { useCapabilitiesStore } from '@/stores/capabilities';

/**
 * useAuthorization — declarative permission checks backed by the Pinia
 * capabilities store.
 *
 * Returns a plain function (not a reactive ref). It remains reactive in
 * templates because it reads a Pinia getter value which is reactive.
 *
 * Usage:
 *   const { can } = useAuthorization();
 *   can('view clients')          → true if user has the permission
 */
export function useAuthorization() {
    const store = useCapabilitiesStore();

    function can(permission: App.Enums.PermissionEnum): boolean {
        return store.hasPermission(permission);
    }

    return { can };
}
