<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ChecksFeatures;
use App\Enums\FeatureEnum;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

final class TenantFeatureAccessorTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // happy: Tenant::hasFeature delegates to bound ChecksFeatures
    // -------------------------------------------------------------------------

    public function test_has_feature_delegates_to_checks_features_binding_and_returns_true(): void
    {
        // Arrange
        $tenant = Tenant::factory()->pro()->create();

        // Act — use real container binding (ConfigFeatureChecker); Objects is in Pro feature list
        $result = $tenant->hasFeature(FeatureEnum::Objects);

        // Assert
        $this->assertTrue($result);
    }

    public function test_has_feature_delegates_to_swapped_mock_and_returns_its_value(): void
    {
        // Arrange — swap the container binding to assert delegation, not config truth
        $tenant = Tenant::factory()->create();

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
