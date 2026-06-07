<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ChecksFeatures;
use App\Enums\FeatureEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Tenant;
use App\Models\User;
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
    // hasFeature — happy paths (owner-resolved plan)
    // -------------------------------------------------------------------------

    public function test_has_feature_returns_true_for_pro_owner_tenant_with_quotes(): void
    {
        // Arrange — tenant owned by Pro user
        $owner = User::factory()->pro()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();

        // Act
        $result = $this->checker()->hasFeature($tenant, FeatureEnum::Quotes);

        // Assert
        $this->assertTrue($result);
    }

    public function test_has_feature_returns_true_for_all_cases_on_enterprise_owner(): void
    {
        // Arrange
        $owner = User::factory()->enterprise()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();

        // Act + Assert
        foreach (FeatureEnum::cases() as $feature) {
            $this->assertTrue(
                $this->checker()->hasFeature($tenant, $feature),
                "Enterprise plan should have feature {$feature->value}",
            );
        }
    }

    // -------------------------------------------------------------------------
    // hasFeature — feature resolves by OWNER plan, not accessor plan
    // -------------------------------------------------------------------------

    public function test_has_feature_resolves_by_owner_plan_not_accessor_plan(): void
    {
        // Arrange — owner is Pro; accessor is a Free user (member of owner's tenant)
        $owner = User::factory()->pro()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();

        // Accessor has Free plan but is a member of the Pro-owner's tenant
        // Features must resolve by owner's Pro plan, not the accessor's plan
        $result = $this->checker()->hasFeature($tenant, FeatureEnum::Quotes);

        // Assert — Pro feature available regardless of who's accessing
        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // hasFeature — failure paths
    // -------------------------------------------------------------------------

    public function test_has_feature_returns_false_for_free_owner_tenant_with_clients(): void
    {
        // Arrange — default factory user = Free plan
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();

        // Act
        $result = $this->checker()->hasFeature($tenant, FeatureEnum::Clients);

        // Assert
        $this->assertFalse($result);
    }

    public function test_has_feature_returns_false_for_starter_owner_with_invoices(): void
    {
        // Arrange
        $owner = User::factory()->create(['subscription_plan' => SubscriptionPlanEnum::Starter->value]);
        $tenant = Tenant::factory()->forOwner($owner)->create();

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
        // Arrange — Pro owner, but remove 'pro' entry from config
        $owner = User::factory()->pro()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();

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

    public function test_get_quota_returns_5_for_starter_owner_multi_user(): void
    {
        // Arrange
        $owner = User::factory()->create(['subscription_plan' => SubscriptionPlanEnum::Starter->value]);
        $tenant = Tenant::factory()->forOwner($owner)->create();

        // Act
        $result = $this->checker()->getQuota($tenant, FeatureEnum::MultiUser);

        // Assert
        $this->assertSame(5, $result);
    }

    public function test_get_quota_returns_20_for_pro_owner_multi_user(): void
    {
        // Arrange
        $owner = User::factory()->pro()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();

        // Act
        $result = $this->checker()->getQuota($tenant, FeatureEnum::MultiUser);

        // Assert
        $this->assertSame(20, $result);
    }

    public function test_get_quota_returns_1_for_free_owner_multi_user(): void
    {
        // Arrange — default User = Free
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();

        // Act
        $result = $this->checker()->getQuota($tenant, FeatureEnum::MultiUser);

        // Assert
        $this->assertSame(1, $result);
    }

    // -------------------------------------------------------------------------
    // getQuota — edge: null (unlimited or no quota concept)
    // -------------------------------------------------------------------------

    public function test_get_quota_returns_null_for_enterprise_owner_multi_user_unlimited(): void
    {
        // Arrange
        $owner = User::factory()->enterprise()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();

        // Act
        $result = $this->checker()->getQuota($tenant, FeatureEnum::MultiUser);

        // Assert
        $this->assertNull($result);
    }

    public function test_get_quota_returns_null_for_pro_owner_clients_feature_no_quota_entry(): void
    {
        // Arrange — Clients has no quota entry on any plan; getQuota returns null
        $owner = User::factory()->pro()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();

        // Act
        $result = $this->checker()->getQuota($tenant, FeatureEnum::Clients);

        // Assert
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // maxTenants — happy paths
    // -------------------------------------------------------------------------

    public function test_max_tenants_returns_1_for_free_user(): void
    {
        // Arrange
        $user = User::factory()->create(); // default Free

        // Act
        $result = $this->checker()->maxTenants($user);

        // Assert
        $this->assertSame(1, $result);
    }

    public function test_max_tenants_returns_2_for_starter_user(): void
    {
        // Arrange
        $user = User::factory()->create(['subscription_plan' => SubscriptionPlanEnum::Starter->value]);

        // Act
        $result = $this->checker()->maxTenants($user);

        // Assert
        $this->assertSame(2, $result);
    }

    public function test_max_tenants_returns_3_for_pro_user(): void
    {
        // Arrange
        $user = User::factory()->pro()->create();

        // Act
        $result = $this->checker()->maxTenants($user);

        // Assert
        $this->assertSame(3, $result);
    }

    public function test_max_tenants_returns_null_for_enterprise_user(): void
    {
        // Arrange
        $user = User::factory()->enterprise()->create();

        // Act
        $result = $this->checker()->maxTenants($user);

        // Assert
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // canCreateTenant — happy paths
    // -------------------------------------------------------------------------

    public function test_can_create_tenant_returns_true_for_free_user_with_zero_owned(): void
    {
        // Arrange — Free user, no owned tenants
        $user = User::factory()->create();

        // Act
        $result = $this->checker()->canCreateTenant($user);

        // Assert
        $this->assertTrue($result);
    }

    public function test_can_create_tenant_returns_true_for_starter_user_with_one_owned(): void
    {
        // Arrange — Starter limit 2, 1 owned
        $user = User::factory()->create(['subscription_plan' => SubscriptionPlanEnum::Starter->value]);
        Tenant::factory()->forOwner($user)->create();

        // Act
        $result = $this->checker()->canCreateTenant($user);

        // Assert
        $this->assertTrue($result);
    }

    public function test_can_create_tenant_returns_true_for_pro_user_with_two_owned(): void
    {
        // Arrange — Pro limit 3, 2 owned
        $user = User::factory()->pro()->create();
        Tenant::factory()->count(2)->forOwner($user)->create();

        // Act
        $result = $this->checker()->canCreateTenant($user);

        // Assert
        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // canCreateTenant — failure paths (at limit)
    // -------------------------------------------------------------------------

    public function test_can_create_tenant_returns_false_for_free_user_at_limit(): void
    {
        // Arrange — Free limit 1, 1 owned
        $user = User::factory()->create();
        Tenant::factory()->forOwner($user)->create();

        // Act
        $result = $this->checker()->canCreateTenant($user);

        // Assert
        $this->assertFalse($result);
    }

    public function test_can_create_tenant_returns_false_for_starter_user_at_limit(): void
    {
        // Arrange — Starter limit 2, 2 owned
        $user = User::factory()->create(['subscription_plan' => SubscriptionPlanEnum::Starter->value]);
        Tenant::factory()->count(2)->forOwner($user)->create();

        // Act
        $result = $this->checker()->canCreateTenant($user);

        // Assert
        $this->assertFalse($result);
    }

    public function test_can_create_tenant_returns_false_for_pro_user_at_limit(): void
    {
        // Arrange — Pro limit 3, 3 owned
        $user = User::factory()->pro()->create();
        Tenant::factory()->count(3)->forOwner($user)->create();

        // Act
        $result = $this->checker()->canCreateTenant($user);

        // Assert
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // canCreateTenant — edge cases
    // -------------------------------------------------------------------------

    public function test_can_create_tenant_returns_true_for_enterprise_user_with_many_owned(): void
    {
        // Arrange — Enterprise = unlimited; 50 owned tenants
        $user = User::factory()->enterprise()->create();
        Tenant::factory()->count(50)->forOwner($user)->create();

        // Act
        $result = $this->checker()->canCreateTenant($user);

        // Assert
        $this->assertTrue($result);
    }

    public function test_can_create_tenant_slot_frees_when_owned_tenant_soft_deleted(): void
    {
        // Arrange — Free user at limit (1 owned), then soft-delete that tenant
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($user)->create();

        $this->assertFalse($this->checker()->canCreateTenant($user));

        // Act — soft-delete the tenant → slot freed
        $tenant->delete();

        // Assert
        $this->assertTrue($this->checker()->canCreateTenant($user));
    }
}
