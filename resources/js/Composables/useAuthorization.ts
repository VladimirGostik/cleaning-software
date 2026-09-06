import { usePageProps } from '@/Composables/usePageProps';

/**
 * Mirrors PermissionEnum::sharedKey() / Laravel's Str::camel for the flat lowercase
 * `[a-z_ ]` permission strings shipped by the BE — 'view all objects' -> 'viewAllObjects'.
 */
export function permissionKey(permission: App.Enums.PermissionEnum): string {
    const [first, ...rest] = permission.split(/[\s_]+/);
    return first + rest.map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join('');
}

export function useAuthorization(): { allows: (permission: App.Enums.PermissionEnum) => boolean } {
    const props = usePageProps();

    function allows(permission: App.Enums.PermissionEnum): boolean {
        return props.value.can[permissionKey(permission)] === true;
    }

    return { allows };
}
