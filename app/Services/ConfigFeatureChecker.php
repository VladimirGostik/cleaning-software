<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ChecksFeatures;
use App\Enums\FeatureEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Tenant;
use App\Models\User;
use RuntimeException;

final readonly class ConfigFeatureChecker implements ChecksFeatures
{
    public function hasFeature(Tenant $tenant, FeatureEnum $feature): bool
    {
        $config = $this->planConfig($tenant);

        return in_array($feature->value, $config['features'], true);
    }

    public function getQuota(Tenant $tenant, FeatureEnum $feature): ?int
    {
        $config = $this->planConfig($tenant);

        return $config['quotas'][$feature->value] ?? null;
    }

    /**
     * Returns all feature values enabled for the tenant's current plan.
     *
     * @return list<string>
     */
    public function featuresFor(Tenant $tenant): array
    {
        return $this->planConfig($tenant)['features'];
    }

    public function maxTenants(User $user): ?int
    {
        $plan = $user->subscription_plan;

        /** @var array<string, array{max_tenants: int|null, features: list<string>, quotas: array<string, int|null>}>|null $plans */
        $plans = config('subscription.plans');

        if (! isset($plans[$plan->value])) {
            if (app()->isProduction()) {
                abort(403, __('app.feature.locked'));
            }

            throw new RuntimeException("Unknown subscription plan: {$plan->value}");
        }

        return $plans[$plan->value]['max_tenants'] ?? null;
    }

    public function canCreateTenant(User $user): bool
    {
        $max = $this->maxTenants($user);

        if ($max === null) {
            return true;
        }

        return $user->ownedTenants()->count() < $max;
    }

    /**
     * @return array{max_tenants: int|null, features: list<string>, quotas: array<string, int|null>}
     */
    private function planConfig(Tenant $tenant): array
    {
        $tenant->loadMissing('owner');

        if ($tenant->owner === null) {
            throw new RuntimeException("Tenant {$tenant->id} has no owner");
        }

        /** @var SubscriptionPlanEnum $plan */
        $plan = $tenant->owner->subscription_plan;

        /** @var array<string, array{max_tenants: int|null, features: list<string>, quotas: array<string, int|null>}>|null $plans */
        $plans = config('subscription.plans');

        if (! isset($plans[$plan->value])) {
            if (app()->isProduction()) {
                abort(403, __('app.feature.locked'));
            }

            throw new RuntimeException("Unknown subscription plan: {$plan->value}");
        }

        return $plans[$plan->value];
    }
}
