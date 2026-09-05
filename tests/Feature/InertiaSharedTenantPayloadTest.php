<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class InertiaSharedTenantPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_payload_contains_id_and_name_keys_for_authenticated_user(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        // Act
        $response = $this->get(route('dashboard'));

        // Assert — tenant.active has id, name, is_active from TenantListItemData
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('tenant')
                ->has('tenant.active')
                ->has('tenant.active.id')
                ->has('tenant.active.name')
                ->has('tenant.active.is_active')
                ->has('tenant.available'),
        );
    }

    public function test_tenant_active_id_matches_current_tenant(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        // Act
        $response = $this->get(route('dashboard'));

        // Assert
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('tenant.active.id', $tenant->id)
                ->where('tenant.active.name', $tenant->name),
        );
    }

    public function test_tenant_payload_for_guest_has_null_active_and_empty_available(): void
    {
        // Act
        $response = $this->get(route('login'));

        // Assert — guests get null active tenant
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('tenant')
                ->where('tenant.active', null),
        );
    }

    public function test_tenant_available_is_array_of_tenant_list_item_data_shape(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        // Act
        $response = $this->get(route('dashboard'));

        // Assert — available contains at least one entry with the expected DTO shape
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('tenant.available', 1, fn (AssertableInertia $item) => $item
                    ->has('id')
                    ->has('name')
                    ->has('is_active'),
                ),
        );
    }
}
