<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TenantColorEnum;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInterface;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    // --- Happy path ---

    public function test_authenticated_user_can_add_tenant(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');

        // Act
        $response = $this->post(route('tenants.store'), [
            'name' => 'Nová Firma s.r.o.',
            'ico' => '55667788',
        ]);

        // Assert
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('flash.success');

        $tenants = $user->tenants()->get();
        $this->assertCount(2, $tenants);

        $newTenant = $tenants->firstWhere('ico', '55667788');
        $this->assertNotNull($newTenant);
        $this->assertSame($user->id, $newTenant->owner_id);

        $this->assertDatabaseHas('tenant_interfaces', ['tenant_id' => $newTenant->id]);
        $this->assertDatabaseHas('tenant_memberships', ['user_id' => $user->id, 'tenant_id' => $newTenant->id]);

        $roles = Role::where('tenant_id', $newTenant->id)->pluck('name');
        $this->assertCount(6, $roles);

        $this->assertSame($newTenant->id, session('active_tenant_id'));
    }

    public function test_add_tenant_with_color_stores_interface_color(): void
    {
        // Arrange
        $this->actingAsTenantUser('Admin');

        // Act
        $this->post(route('tenants.store'), [
            'name' => 'Modrá Firma s.r.o.',
            'ico' => '22334455',
            'color' => TenantColorEnum::Blue600->value,
        ])->assertRedirect();

        // Assert
        $tenant = Tenant::where('ico', '22334455')->firstOrFail();
        $interface = TenantInterface::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(TenantColorEnum::Blue600, $interface->color);
    }

    public function test_add_tenant_copy_settings_copies_color_from_active_tenant(): void
    {
        // Arrange
        $this->actingAsTenantUser('Admin');
        $activeTenantId = session('active_tenant_id');

        TenantInterface::firstOrCreate(
            ['tenant_id' => $activeTenantId],
            ['color' => TenantColorEnum::Violet600->value],
        );

        // Act
        $this->post(route('tenants.store'), [
            'name' => 'Kopírovaná Firma s.r.o.',
            'ico' => '33445566',
            'copy_settings' => true,
        ])->assertRedirect();

        // Assert
        $newTenant = Tenant::where('ico', '33445566')->firstOrFail();
        $interface = TenantInterface::where('tenant_id', $newTenant->id)->firstOrFail();
        $this->assertSame(TenantColorEnum::Violet600, $interface->color);
    }

    public function test_add_tenant_with_leader_email_creates_invitation(): void
    {
        // Arrange
        $this->actingAsTenantUser('Admin');

        // Act
        $this->post(route('tenants.store'), [
            'name' => 'Firma s vedúcou s.r.o.',
            'ico' => '44556677',
            'leader_email' => 'veduca@firma.sk',
        ])->assertRedirect();

        // Assert
        $tenant = Tenant::where('ico', '44556677')->firstOrFail();
        $this->assertDatabaseHas('tenant_invitations', [
            'tenant_id' => $tenant->id,
            'email' => 'veduca@firma.sk',
            'role_name' => 'Vedúca',
        ]);
    }

    // --- No quota (entitlement layer removed) ---

    public function test_owner_of_many_tenants_can_create_another_without_limit(): void
    {
        // Arrange — owns 1 tenant from actingAsTenantUser + 9 more created directly.
        $user = $this->actingAsTenantUser('Admin');
        Tenant::factory()->count(9)->forOwner($user)->create();

        // Act — creating the 11th tenant must never be blocked by a quota.
        $response = $this->post(route('tenants.store'), [
            'name' => 'Jedenásta Firma s.r.o.',
            'ico' => '77889900',
        ]);

        // Assert
        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('tenants', ['ico' => '77889900']);
    }

    // --- Auth gate ---

    public function test_guest_cannot_add_tenant(): void
    {
        // Act
        $response = $this->post(route('tenants.store'), [
            'name' => 'Neautorizovaná s.r.o.',
            'ico' => '99887766',
        ]);

        // Assert
        $response->assertRedirect(route('login'));
    }
}
