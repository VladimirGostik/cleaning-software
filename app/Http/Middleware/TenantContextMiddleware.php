<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant for the current request and binds it into the
 * container as `current_tenant_id`. The BelongsToTenant global scope reads
 * this binding to filter all domain queries. Spatie Permission also gets
 * the tenant_id as its team context so role/permission lookups stay scoped.
 *
 * Resolution order: `X-Tenant-Id` header (API clients) → session `active_tenant_id`
 * (web) → first active membership ordered by `joined_at`.
 */
final class TenantContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $tenantId = $this->resolveFromHeader($request, $user);

        if ($tenantId === null) {
            $tenantId = $request->hasSession() ? $this->resolveFromSession($request, $user) : null;
        }

        if ($tenantId === null) {
            /** @var string|null $tenantId */
            $tenantId = $user->memberships()
                ->where('is_active', true)
                ->orderBy('joined_at')
                ->value('tenant_id');
        }

        if ($tenantId !== null) {
            app()->instance('current_tenant_id', $tenantId);
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

            if ($request->hasSession()) {
                $request->session()->put('active_tenant_id', $tenantId);
            }
        }

        return $next($request);
    }

    private function resolveFromHeader(Request $request, User $user): ?string
    {
        $header = $request->header('X-Tenant-Id');

        if ($header === null) {
            return null;
        }

        abort_unless(Str::isUuid($header) && $user->hasActiveMembership($header), 403, __('app.tenant_forbidden'));

        return $header;
    }

    private function resolveFromSession(Request $request, User $user): ?string
    {
        $tenantId = $request->session()->get('active_tenant_id');

        if (! is_string($tenantId) || ! $user->hasActiveMembership($tenantId)) {
            return null;
        }

        return $tenantId;
    }
}
