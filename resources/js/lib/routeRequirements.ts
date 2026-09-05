/**
 * Route requirement map for the Inertia navigation guard.
 *
 * Inertia has no Vue Router route meta — requirements are keyed by URL
 * pathname prefix. The guard matches the longest prefix.
 *
 * NOTE: This is UX-only. Real enforcement is on the BE via `#[Authorize]`
 * policies. This guard only redirects/hides — it is NOT a security boundary.
 *
 * Add an entry here when a new protected route is introduced. The value
 * shape mirrors `<Can permission />` props.
 */
export interface RouteRequirement {
    permission?: App.Enums.PermissionEnum;
}

/**
 * Path-prefix → requirement map.
 * Evaluated from longest prefix to shortest — add more specific paths first.
 */
export const routeRequirements: Record<string, RouteRequirement> = {
    '/clients': { permission: 'view clients' },
    '/objects': { permission: 'view objects' },
    '/quotes': { permission: 'view quotes' },
    '/contracts': { permission: 'view contracts' },
    '/schedule': { permission: 'view schedule' },
    '/employees': { permission: 'view employees' },
    '/invoices': { permission: 'view invoices' },
    '/settings/invoicing': { permission: 'manage billing settings' },
    '/templates': { permission: 'view templates' },
};

/**
 * Returns the requirement for a given pathname, or null if unrestricted.
 * Matches the longest registered prefix.
 */
export function matchRequirement(pathname: string): RouteRequirement | null {
    const sorted = Object.keys(routeRequirements).sort((a, b) => b.length - a.length);

    for (const prefix of sorted) {
        if (pathname === prefix || pathname.startsWith(prefix + '/')) {
            return routeRequirements[prefix];
        }
    }

    return null;
}
