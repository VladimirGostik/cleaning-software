<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\FeatureEnum;
use App\Models\Tenant;
use App\Models\User;

interface ChecksFeatures
{
    public function hasFeature(Tenant $tenant, FeatureEnum $feature): bool;

    /** Returns the plan quota limit for the given feature. null = unlimited or no quota concept. */
    public function getQuota(Tenant $tenant, FeatureEnum $feature): ?int;

    /**
     * Returns all feature values enabled for the tenant's current plan.
     *
     * @return list<string>
     */
    public function featuresFor(Tenant $tenant): array;

    /** Returns the maximum number of tenants the user may own. null = unlimited. */
    public function maxTenants(User $user): ?int;

    /** Returns true if the user may create another tenant. */
    public function canCreateTenant(User $user): bool;
}
