<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

final class RequiresTenantFeatureMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register a throwaway test route protected by the 'feature' middleware.
        // Using 'quotes' (a Pro-level feature) as the representative gated resource.
        Route::get('/_test/gated-quotes', fn () => response('ok', 200))
            ->middleware(['web', 'feature:quotes'])
            ->name('test.gated.quotes');

        // A second route using 'objects' (available on Starter+) for isolation test.
        Route::get('/_test/gated-objects', fn () => response('ok', 200))
            ->middleware(['web', 'feature:objects'])
            ->name('test.gated.objects');

        // A route with an unknown/bogus feature param to exercise the fail-loud branch.
        Route::get('/_test/gated-bogus', fn () => response('ok', 200))
            ->middleware(['web', 'feature:bogus'])
            ->name('test.gated.bogus');
    }

    // -------------------------------------------------------------------------
    // happy: plan includes feature → request passes
    // -------------------------------------------------------------------------

    public function test_request_passes_when_tenant_plan_includes_feature(): void
    {
        // Arrange — Pro plan includes Quotes
        $tenant = Tenant::factory()->pro()->create();
        $this->bindTenantContext($tenant);

        // Act
        $response = $this->get('/_test/gated-quotes');

        // Assert
        $response->assertOk();
        $response->assertSee('ok');
    }

    // -------------------------------------------------------------------------
    // failure: plan lacks feature → 403 with localized message
    // -------------------------------------------------------------------------

    public function test_request_returns_403_when_tenant_plan_lacks_feature(): void
    {
        // Arrange — Free plan has no features
        $tenant = Tenant::factory()->create(); // default = Free
        $this->bindTenantContext($tenant);

        // Act
        $response = $this->get('/_test/gated-quotes');

        // Assert
        $response->assertForbidden();
        $response->assertSee(__('app.feature.locked'));
    }

    // -------------------------------------------------------------------------
    // edge: no current_tenant_id bound → 403
    // -------------------------------------------------------------------------

    public function test_request_returns_403_when_no_current_tenant_id_bound(): void
    {
        // Arrange — deliberately do NOT bind current_tenant_id

        // Act
        $response = $this->get('/_test/gated-quotes');

        // Assert
        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // edge: invalid feature param → RuntimeException in non-prod (testing env)
    // -------------------------------------------------------------------------

    public function test_invalid_feature_param_throws_runtime_exception_in_non_prod(): void
    {
        // Arrange
        $tenant = Tenant::factory()->pro()->create();
        $this->bindTenantContext($tenant);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unknown feature: bogus/');

        // Act
        $this->withoutExceptionHandling()->get('/_test/gated-bogus');
    }

    // -------------------------------------------------------------------------
    // isolation: two tenants on different plans — no current_tenant_id leakage
    // -------------------------------------------------------------------------

    public function test_two_tenants_on_different_plans_resolve_own_entitlement(): void
    {
        // Arrange
        $freeTenant = Tenant::factory()->create();          // Free — no features
        $proTenant = Tenant::factory()->pro()->create();   // Pro — has Objects

        // Act + Assert: Free tenant is denied
        $this->bindTenantContext($freeTenant);
        $this->get('/_test/gated-objects')->assertForbidden();

        // Act + Assert: Pro tenant is permitted — no leakage from previous request
        $this->bindTenantContext($proTenant);
        $this->get('/_test/gated-objects')->assertOk();
    }

    // -------------------------------------------------------------------------
    // helper
    // -------------------------------------------------------------------------

    private function bindTenantContext(Tenant $tenant): void
    {
        app()->instance('current_tenant_id', $tenant->id);
    }
}
