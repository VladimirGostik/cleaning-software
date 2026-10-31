<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\PermissionEnum;
use App\Models\User;
use App\Services\Dashboard\TenantVisibility;
use App\Services\Tenancy\TenantPermissionSnapshot;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
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
    /**
     * @param  Closure(Request): Response  $next
     */
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
            $tenantId = $this->firstMembershipTenant($user, $this->isMobileToken($user));
        }

        if ($tenantId !== null) {
            app()->instance('current_tenant_id', $tenantId);
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

            if ($request->hasSession()) {
                $request->session()->put('active_tenant_id', $tenantId);
            }
        }

        if ($tenantId !== null && $this->isMobileToken($user)) {
            abort_unless($user->can(PermissionEnum::AccessMobileApp->value), 403, __('app.mobile_forbidden'));
        }

        return $next($request);
    }

    private function firstMembershipTenant(User $user, bool $mobileOnly): ?string
    {
        $query = $user->memberships()->where('is_active', true)->orderBy('joined_at');

        if ($mobileOnly) {
            $allowed = TenantVisibility::idsWhere(
                app(TenantPermissionSnapshot::class)->for($user),
                fn (TenantVisibility $visibility): bool => $visibility->mobileAccess,
            );

            if ($allowed !== []) {
                $query->whereIn('tenant_id', $allowed);
            }
        }

        /** @var string|null $tenantId */
        $tenantId = $query->value('tenant_id');

        return $tenantId;
    }

    private function isMobileToken(User $user): bool
    {
        /** @var PersonalAccessToken|TransientToken|null $token */
        $token = $user->currentAccessToken();

        return $token instanceof PersonalAccessToken && $token->can('mobile');
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
