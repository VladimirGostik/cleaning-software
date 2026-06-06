<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ChecksFeatures;
use App\Enums\FeatureEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Tenant;
use App\Services\ConfigFeatureChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class ConfigFeatureCheckerTest extends TestCase
{
    use RefreshDatabase;

    private function checker(): ChecksFeatures
    {
        return app(ChecksFeatures::class);
    }

    // -------------------------------------------------------------------------
    // hasFeature — happy paths
    // -------------------------------------------------------------------------

    public function test_has_feature_returns_true_for_pro_tenant_with_quotes(): void
    {
        // Arrange
        $tenant = Tenant::factory()->pro()->create();

        // Act
        $result = $this->checker()->hasFeature($tenant, FeatureEnum::Quotes);

        // Assert
        $this->assertTrue($result);
    }

    public function test_has_feature_returns_true_for_all_cases_on_enterprise_plan(): void
    {
        // Arrange
        $tenant = Tenant::factory()->enterprise()->create();

        // Act + Assert
        foreach (FeatureEnum::cases() as $feature) {
            $this->assertTrue(
                $this->checker()->hasFeature($tenant, $feature),
                "Enterprise plan should have feature {$feature->value}",
            );
        }
    }

    // -------------------------------------------------------------------------
    // hasFeature — failure paths
    // -------------------------------------------------------------------------

    public function test_has_feature_returns_false_for_free_tenant_with_clients(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create(); // default = Free

        // Act
        $result = $this->checker()->hasFeature($tenant, FeatureEnum::Clients);

        // Assert
        $this->assertFalse($result);
    }

    public function test_has_feature_returns_false_for_starter_tenant_with_invoices(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create(['subscription_plan' => SubscriptionPlanEnum::Starter->value]);

        // Act
        $result = $this->checker()->hasFeature($tenant, FeatureEnum::Invoices);

        // Assert
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // hasFeature — edge: plan value absent from config throws in non-prod
    // -------------------------------------------------------------------------

    public function test_has_feature_throws_runtime_exception_when_plan_missing_from_config(): void
    {
        // Arrange — create a Pro tenant but surgically remove 'pro' entry from config
        // so ConfigFeatureChecker hits the unknown-plan guard branch
        $tenant = Tenant::factory()->pro()->create();

        $plans = config('subscription.plans');
        unset($plans[SubscriptionPlanEnum::Pro->value]);
        config(['subscription.plans' => $plans]);

        $checker = new ConfigFeatureChecker;

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unknown subscription plan: pro/');

        $checker->hasFeature($tenant, FeatureEnum::Clients);
    }

    // -------------------------------------------------------------------------
    // getQuota — happy paths
    // -------------------------------------------------------------------------

    public function test_get_quota_returns_5_for_starter_multi_user(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create(['subscription_plan' => SubscriptionPlanEnum::Starter->value]);

        // Act
        $result = $this->checker()->getQuota($tenant, FeatureEnum::MultiUser);

        // Assert
        $this->assertSame(5, $result);
    }

    public function test_get_quota_returns_20_for_pro_multi_user(): void
    {
        // Arrange
        $tenant = Tenant::factory()->pro()->create();

        // Act
        $result = $this->checker()->getQuota($tenant, FeatureEnum::MultiUser);

        // Assert
        $this->assertSame(20, $result);
    }

    public function test_get_quota_returns_1_for_free_multi_user(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create(); // default = Free

        // Act
        $result = $this->checker()->getQuota($tenant, FeatureEnum::MultiUser);

        // Assert
        $this->assertSame(1, $result);
    }

    // -------------------------------------------------------------------------
    // getQuota — edge: null (unlimited or no quota concept)
    // -------------------------------------------------------------------------

    public function test_get_quota_returns_null_for_enterprise_multi_user_unlimited(): void
    {
        // Arrange
        $tenant = Tenant::factory()->enterprise()->create();

        // Act
        $result = $this->checker()->getQuota($tenant, FeatureEnum::MultiUser);

        // Assert
        $this->assertNull($result);
    }

    public function test_get_quota_returns_null_for_pro_tenant_clients_feature_no_quota_entry(): void
    {
        // Arrange — Clients has no quota entry on any plan; getQuota returns null
        $tenant = Tenant::factory()->pro()->create();

        // Act
        $result = $this->checker()->getQuota($tenant, FeatureEnum::Clients);

        // Assert
        $this->assertNull($result);
    }
}
