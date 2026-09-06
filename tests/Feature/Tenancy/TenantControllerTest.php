<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\InvitationCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class TenantControllerTest extends TestCase
{
    use RefreshDatabase;

    private function withActiveMembership(User $user): Tenant
    {
        $tenant = Tenant::factory()->forOwner($user)->create();
        TenantInterface::factory()->create(['tenant_id' => $tenant->id, 'color' => '#2563EB']);
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);
        $this->bindTenant($tenant);

        return $tenant;
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_tenant_and_switches_active_session(): void
    {
        $user = User::factory()->create();
        $this->withActiveMembership($user);

        $response = $this->actingAs($user)->post('/tenants', [
            'name' => 'New Co',
            'ico' => '87654321',
        ]);

        $response->assertRedirect(route('dashboard'));
        $newTenant = Tenant::where('name', 'New Co')->firstOrFail();
        $this->assertSame($newTenant->id, session('active_tenant_id'));
        $this->assertSame($user->id, $newTenant->owner_id);
    }

    public function test_store_with_copy_settings_copies_color_from_active_tenant(): void
    {
        $user = User::factory()->create();
        $this->withActiveMembership($user);

        $this->actingAs($user)->post('/tenants', [
            'name' => 'Copy Co',
            'ico' => '11112222',
            'copy_settings' => true,
        ]);

        $newTenant = Tenant::where('name', 'Copy Co')->firstOrFail();
        $this->assertSame('#2563EB', $newTenant->interface->color->value);
    }

    public function test_store_with_copy_settings_and_no_source_color_is_null(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($user)->create();
        TenantInterface::factory()->create(['tenant_id' => $tenant->id, 'color' => null]);
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);
        $this->bindTenant($tenant);

        $this->actingAs($user)->post('/tenants', [
            'name' => 'No Color Co',
            'ico' => '33334444',
            'copy_settings' => true,
        ]);

        $newTenant = Tenant::where('name', 'No Color Co')->firstOrFail();
        $this->assertNull($newTenant->interface->color);
    }

    public function test_store_with_leader_email_creates_invitation(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->withActiveMembership($user);

        $this->actingAs($user)->post('/tenants', [
            'name' => 'Leader Co',
            'ico' => '55556666',
            'leader_email' => 'leader@example.com',
        ]);

        $tenant = Tenant::where('name', 'Leader Co')->firstOrFail();
        $this->assertDatabaseHas('tenant_invitations', ['tenant_id' => $tenant->id, 'email' => 'leader@example.com', 'role_name' => 'Vedúca']);
        Notification::assertSentOnDemand(InvitationCreated::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'leader@example.com');
    }

    public function test_store_is_unreachable_by_guest(): void
    {
        $response = $this->post('/tenants', ['name' => 'Guest Co', 'ico' => '99998888']);

        $response->assertRedirect(route('login'));
    }

    public function test_store_with_invalid_leader_email_returns_422(): void
    {
        $user = User::factory()->create();
        $this->withActiveMembership($user);

        $response = $this->actingAs($user)->post('/tenants', [
            'name' => 'Bad Email Co',
            'ico' => '77778888',
            'leader_email' => 'not-an-email',
        ]);

        $response->assertInvalid(['leader_email']);
    }

    // ── switch ────────────────────────────────────────────────────────────────

    public function test_switch_to_member_tenant_succeeds(): void
    {
        $user = User::factory()->create();
        $this->withActiveMembership($user);
        $other = Tenant::factory()->forOwner($user)->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $other->id, 'is_active' => true, 'joined_at' => now()]);

        $response = $this->actingAs($user)->post("/tenants/{$other->id}/switch");

        $response->assertRedirect(route('dashboard'));
        $this->assertSame($other->id, session('active_tenant_id'));
    }

    public function test_switch_to_non_member_tenant_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->withActiveMembership($user);
        $foreign = Tenant::factory()->create();

        $response = $this->actingAs($user)->post("/tenants/{$foreign->id}/switch");

        $response->assertForbidden();
    }

    public function test_switch_to_inactive_membership_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->withActiveMembership($user);
        $other = Tenant::factory()->forOwner($user)->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $other->id, 'is_active' => false, 'joined_at' => now()]);

        $response = $this->actingAs($user)->post("/tenants/{$other->id}/switch");

        $response->assertForbidden();
    }

    public function test_switch_to_inactive_tenant_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->withActiveMembership($user);
        $other = Tenant::factory()->forOwner($user)->create(['is_active' => false]);
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $other->id, 'is_active' => true, 'joined_at' => now()]);

        $response = $this->actingAs($user)->post("/tenants/{$other->id}/switch");

        $response->assertForbidden();
    }
}
