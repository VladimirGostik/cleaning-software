<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ChecksFeatures;
use App\Enums\FeatureEnum;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

final class TenantFeatureAccessorTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // happy: Tenant::hasFeature delegates to bound ChecksFeatures (owner-resolved)
    // -------------------------------------------------------------------------

    public function test_has_feature_delegates_to_checks_features_binding_and_returns_true(): void
    {
        // Arrange — tenant owned by a Pro user; Objects is in Pro feature list
        $owner = User::factory()->pro()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();

        // Act — use real container binding (ConfigFeatureChecker)
        $result = $tenant->hasFeature(FeatureEnum::Objects);

        // Assert
        $this->assertTrue($result);
    }

    public function test_has_feature_delegates_to_swapped_mock_and_returns_its_value(): void
    {
        // Arrange — swap the container binding to assert delegation, not config truth
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();

        $this->mock(ChecksFeatures::class, function (MockInterface $mock) use ($tenant): void {
            $mock->shouldReceive('hasFeature')
                ->once()
                ->with($tenant, FeatureEnum::Objects)
                ->andReturn(true);
        });

        // Act
        $result = $tenant->hasFeature(FeatureEnum::Objects);

        // Assert
        $this->assertTrue($result);
    }
}
