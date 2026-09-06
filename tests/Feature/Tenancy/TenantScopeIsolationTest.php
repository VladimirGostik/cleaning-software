<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\TemporaryUpload;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantScopeIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_tenant_model_is_invisible_across_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->bindTenant($tenantA);
        $invitationA = TenantInvitation::factory()->create(['tenant_id' => $tenantA->id]);

        $this->bindTenant($tenantB);
        $invitationB = TenantInvitation::factory()->create(['tenant_id' => $tenantB->id]);

        $visible = TenantInvitation::all()->pluck('id');

        $this->assertContains($invitationB->id, $visible);
        $this->assertNotContains($invitationA->id, $visible);
    }

    public function test_creating_hook_fills_tenant_id_from_bound_context(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        // Created without an explicit tenant_id — the BelongsToTenant `creating` hook
        // must fill it from the bound container context.
        $invitation = TenantInvitation::create([
            'email' => 'invitee@example.com',
            'role_name' => 'Vedúca',
            'token' => str_repeat('a', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertSame($tenant->id, $invitation->tenant_id);
    }

    public function test_unbound_context_returns_rows_from_every_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->bindTenant($tenantA);
        TenantInvitation::factory()->create(['tenant_id' => $tenantA->id]);

        $this->bindTenant($tenantB);
        TenantInvitation::factory()->create(['tenant_id' => $tenantB->id]);

        app()->forgetInstance('current_tenant_id');

        $this->assertSame(2, TenantInvitation::count());
    }

    public function test_temporary_upload_is_scoped_per_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->bindTenant($tenantA);
        $uploadA = TemporaryUpload::create(['session_id' => 'sess-a']);

        $this->bindTenant($tenantB);
        $uploadB = TemporaryUpload::create(['session_id' => 'sess-b']);

        $visible = TemporaryUpload::pluck('id');

        $this->assertContains($uploadB->id, $visible);
        $this->assertNotContains($uploadA->id, $visible);
    }

    public function test_tenant_membership_is_not_globally_scoped(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        TenantMembership::create(['user_id' => $userA->id, 'tenant_id' => $tenantA->id, 'is_active' => true, 'joined_at' => now()]);
        TenantMembership::create(['user_id' => $userB->id, 'tenant_id' => $tenantB->id, 'is_active' => true, 'joined_at' => now()]);

        $this->bindTenant($tenantA);

        // TenantMembership does NOT use BelongsToTenant (it IS the tenancy definition) —
        // binding a tenant must not hide rows belonging to another tenant.
        $this->assertSame(2, TenantMembership::count());
    }
}
