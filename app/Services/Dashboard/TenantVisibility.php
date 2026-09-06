<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Tenant;

/**
 * Per-tenant permission snapshot for the acting user, resolved once under that tenant's
 * permission team id (see `DashboardService::visibilityFor`). Plain value object — not a
 * Spatie Data DTO, never crosses the Inertia boundary.
 */
final readonly class TenantVisibility
{
    public function __construct(
        public Tenant $tenant,
        public ?string $membershipId,
        public bool $invoices,
        public bool $schedule,
        public bool $scheduleAll,
        public bool $contracts,
        public bool $quotes,
        public bool $employees,
        public bool $clients,
        public bool $objects,
        public bool $billingSettings,
        public bool $createInvoices,
    ) {}

    /**
     * @param  list<self>  $all
     * @param  callable(self): bool  $predicate
     * @return list<string>
     */
    public static function idsWhere(array $all, callable $predicate): array
    {
        return array_values(array_map(
            fn (self $visibility): string => $visibility->tenant->id,
            array_filter($all, $predicate),
        ));
    }
}
