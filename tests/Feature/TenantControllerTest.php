<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SubscriptionPlanEnum;
use App\Enums\TenantColorEnum;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\User;
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
        // Arrange — actingAsTenantUser creates a Free user by default; upgrade to Pro so limit ≥ 2
        $user = $this->actingAsTenantUser('Vlastník');
        $user->forceFill(['subscription_plan' => SubscriptionPlanEnum::Pro->value])->save();

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
        $user = $this->actingAsTenantUser('Vlastník');
        $user->forceFill(['subscription_plan' => SubscriptionPlanEnum::Pro->value])->save();

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
        $user = $this->actingAsTenantUser('Vlastník');
        $user->forceFill(['subscription_plan' => SubscriptionPlanEnum::Pro->value])->save();
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
        $user = $this->actingAsTenantUser('Vlastník');
        $user->forceFill(['subscription_plan' => SubscriptionPlanEnum::Pro->value])->save();

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

    // --- Quota guard ---

    public function test_pro_user_with_two_owned_tenants_can_create_third(): void
    {
        // Arrange — Pro limit 3; user owns existing tenant (from actingAsTenantUser) + 1 more
        $user = $this->actingAsTenantUser('Vlastník');
        $user->forceFill(['subscription_plan' => SubscriptionPlanEnum::Pro->value])->save();

        // Create a second owned tenant directly (simulating user already has 2)
        Tenant::factory()->forOwner($user)->create();

        // User now owns 2 tenants; Pro limit = 3 → can create
        $response = $this->post(route('tenants.store'), [
            'name' => 'Tretia Firma s.r.o.',
            'ico' => '77889900',
        ]);

        // Assert
        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('tenants', ['ico' => '77889900']);
    }

    public function test_free_user_with_one_owned_tenant_gets_403_on_create(): void
    {
        // Arrange — Free limit 1; actingAsTenantUser creates 1 tenant → already at limit
        $user = $this->actingAsTenantUser('Vlastník');
        // User has Free plan (default factory); owns 1 tenant from setUp

        // Act
        $response = $this->post(route('tenants.store'), [
            'name' => 'Nepovolená Firma s.r.o.',
            'ico' => '11112222',
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseMissing('tenants', ['ico' => '11112222']);
    }

    public function test_enterprise_user_is_never_blocked_by_quota(): void
    {
        // Arrange — Enterprise = unlimited
        $user = $this->actingAsTenantUser('Vlastník');
        $user->forceFill(['subscription_plan' => SubscriptionPlanEnum::Enterprise->value])->save();

        // Pre-create many owned tenants to push well past any finite limit
        Tenant::factory()->count(10)->forOwner($user)->create();

        // Act
        $response = $this->post(route('tenants.store'), [
            'name' => 'Enterprise Firma s.r.o.',
            'ico' => '55544433',
        ]);

        // Assert
        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('tenants', ['ico' => '55544433']);
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
