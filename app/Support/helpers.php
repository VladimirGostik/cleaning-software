<?php

declare(strict_types=1);

use App\Support\Formats;
use Carbon\CarbonInterface;

if (! function_exists('get_client_ip')) {
    function get_client_ip(): string
    {
        return request()->ip() ?? '';
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

if (! function_exists('format_quantity')) {
    /**
     * Trims trailing zeros off a 2-dp quantity: `111.00` -> `111`, `1.50` -> `1,5`,
     * `0.25` -> `0,25`. Kept as a helper (not inline `@php`) so it stays unit-testable
     * and reusable once modern/minimal adopt the same trimming.
     */
    function format_quantity(float $quantity): string
    {
        $formatted = number_format($quantity, 2, ',', "\u{00A0}");

        return rtrim(rtrim($formatted, '0'), ',');
    }
}

if (! function_exists('format_date')) {
    function format_date(?CarbonInterface $date): string
    {
        return $date?->format(Formats::DATE) ?? '—';
    }
}

if (! function_exists('format_phone')) {
    function format_phone(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);

        if (preg_match('/^\+421(\d{3})(\d{3})(\d{3})$/', $trimmed, $m) === 1) {
            return "+421 {$m[1]} {$m[2]} {$m[3]}";
        }

        return $trimmed;
    }
}
