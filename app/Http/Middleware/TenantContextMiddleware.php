<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant for the current request and binds it into the
 * container as `current_tenant_id`. The BelongsToTenant global scope reads
 * this binding to filter all domain queries. Spatie Permission also gets
 * the tenant_id as its team context so role/permission lookups stay scoped.
 */
final class TenantContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $tenantId = $request->session()->get('active_tenant_id');

        if ($tenantId === null || ! $user->memberships()->where('tenant_id', $tenantId)->where('is_active', true)->exists()) {
            $tenantId = $user->memberships()
                ->where('is_active', true)
                ->orderBy('joined_at')
                ->value('tenant_id');

            if ($tenantId !== null) {
                $request->session()->put('active_tenant_id', $tenantId);
            }
        }

        if ($tenantId !== null) {
            app()->instance('current_tenant_id', $tenantId);
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        }

        return $next($request);
    }
}
