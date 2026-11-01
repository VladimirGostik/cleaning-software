<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Facade;
use Spatie\Permission\PermissionRegistrar;

final readonly class ToolContext
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @param  array<string, mixed>|null  $input  Installs a synthetic request carrying these inputs, so DTO rules and
     *                                            QueryBuilder read them exactly as they would from a web request.
     * @return T
     */
    public function run(User $user, string $tenantId, Closure $callback, ?array $input = null): mixed
    {
        $this->assertActiveMember($user, $tenantId);

        $hadTenant = app()->bound('current_tenant_id');
        /** @var string|null $previousTenant */
        $previousTenant = $hadTenant ? app('current_tenant_id') : null;
        $previousTeam = $this->registrar->getPermissionsTeamId();
        $guard = auth()->guard();
        $previousUser = $guard->hasUser() ? $guard->user() : null;
        $swappedUser = ! $guard->hasUser() || $guard->id() !== $user->id;

        app()->instance('current_tenant_id', $tenantId);
        $this->registrar->setPermissionsTeamId($tenantId);
        $this->resetPermissionCache($user);

        if ($swappedUser) {
            $guard->setUser($user);
        }

        try {
            return $input === null ? $callback() : $this->withRequest($user, $input, [], $callback);
        } finally {
            if ($swappedUser) {
                $this->restoreUser($guard, $previousUser);
            }

            if ($hadTenant) {
                app()->instance('current_tenant_id', $previousTenant);
            } else {
                app()->forgetInstance('current_tenant_id');
            }

            $this->registrar->setPermissionsTeamId($previousTeam);
            $this->resetPermissionCache($user);
        }
    }

    /**
     * @template T
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, object|string|null>  $routeParameters  Route parameters the DTO rules read via `request()->route(...)`.
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withRequest(User $user, array $input, array $routeParameters, Closure $callback): mixed
    {
        /** @var Request $previous */
        $previous = app('request');

        $request = Request::create($previous->getSchemeAndHttpHost().'/ai-tool', 'POST', $input);
        $request->setUserResolver(fn (): User => $user);

        if ($routeParameters !== []) {
            $route = new Route(['POST'], '/ai-tool', fn (): null => null);
            $route->bind($request);

            foreach ($routeParameters as $key => $value) {
                $route->setParameter($key, $value);
            }

            $request->setRouteResolver(fn (): Route => $route);
        }

        $this->swapRequest($request);

        try {
            return $callback();
        } finally {
            $this->swapRequest($previous);
        }
    }

    private function assertActiveMember(User $user, string $tenantId): void
    {
        $active = Tenant::withoutGlobalScopes()->whereKey($tenantId)->where('is_active', true)->exists();

        if (! $active || ! $user->hasActiveMembership($tenantId)) {
            throw new AuthorizationException(__('app.tool_company_forbidden'));
        }
    }

    private function resetPermissionCache(User $user): void
    {
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    private function swapRequest(Request $request): void
    {
        app()->instance('request', $request);
        Facade::clearResolvedInstance('request');
    }

    private function restoreUser(Guard $guard, mixed $previousUser): void
    {
        if ($previousUser instanceof User) {
            $guard->setUser($previousUser);

            return;
        }

        $guard->forgetUser();
    }
}
