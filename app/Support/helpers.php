<?php

declare(strict_types=1);

if (! function_exists('get_client_ip')) {
    function get_client_ip(): string
    {
        $request = request();

        return $request->header('X-Real-IP') ?? $request->ip() ?? '';
    }
}

if (! function_exists('current_tenant_id')) {
    /**
     * Typed accessor for the tenant id bound into the container by
     * `TenantContextMiddleware` / test helpers — avoids `(string) app(...)`
     * casts of `mixed` at every call site. Throws when unbound; callers must
     * only use this where a tenant is guaranteed to be bound (HTTP request
     * behind `tenant.required`, or a test that called `bindTenant()`).
     */
    function current_tenant_id(): string
    {
        if (! app()->bound('current_tenant_id')) {
            throw new RuntimeException('current_tenant_id is not bound.');
        }

        /** @var string $tenantId */
        $tenantId = app('current_tenant_id');

        return $tenantId;
    }
}
