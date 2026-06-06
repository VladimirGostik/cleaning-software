<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\FeatureEnum;
use App\Models\Tenant;

interface ChecksFeatures
{
    public function hasFeature(Tenant $tenant, FeatureEnum $feature): bool;

    /** Returns the plan quota limit for the given feature. null = unlimited or no quota concept. */
    public function getQuota(Tenant $tenant, FeatureEnum $feature): ?int;
}
