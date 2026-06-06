<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ChecksFeatures;
use App\Enums\FeatureEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Tenant;
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
     * @return array{features: list<string>, quotas: array<string, int|null>}
     */
    private function planConfig(Tenant $tenant): array
    {
        /** @var SubscriptionPlanEnum $plan */
        $plan = $tenant->subscription_plan;

        /** @var array<string, array{features: list<string>, quotas: array<string, int|null>}>|null $plans */
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
