<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class TenantContextMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function addMember(User $user, Tenant $tenant, bool $active = true): void
    {
        TenantMembership::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'is_active' => $active,
            'joined_at' => now(),
        ]);
    }

    public function test_session_tenant_wins_over_first_membership(): void
    {
        $user = User::factory()->create();
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();
        $this->addMember($user, $first);
        $this->addMember($user, $second);

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $second->id])
            ->get('/profile')
            ->assertOk();

        $this->assertSame($second->id, session('active_tenant_id'));
    }

    public function test_invalid_session_tenant_falls_back_to_first_active_membership(): void
    {
        $user = User::factory()->create();
        $foreign = Tenant::factory()->create();
        $own = Tenant::factory()->create();
        $this->addMember($user, $own);

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $foreign->id])
            ->get('/profile')
            ->assertOk();

        $this->assertSame($own->id, session('active_tenant_id'));
    }

    public function test_inactive_membership_in_session_is_skipped(): void
    {
        $user = User::factory()->create();
        $inactiveTenant = Tenant::factory()->create();
        $activeTenant = Tenant::factory()->create();
        $this->addMember($user, $inactiveTenant, active: false);
        $this->addMember($user, $activeTenant);

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $inactiveTenant->id])
            ->get('/profile')
            ->assertOk();

        $this->assertSame($activeTenant->id, session('active_tenant_id'));
    }

    public function test_api_header_with_valid_membership_is_accepted(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->addMember($user, $tenant);
        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Tenant-Id' => $tenant->id])->getJson('/api/me');

        $response->assertOk();
        $response->assertJsonPath('activeTenantId', $tenant->id);
    }

    public function test_api_header_for_foreign_tenant_is_forbidden(): void
    {
        $user = User::factory()->create();
        $own = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();
        $this->addMember($user, $own);
        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Tenant-Id' => $foreign->id])->getJson('/api/me');

        $response->assertForbidden();
    }

    public function test_api_header_with_malformed_uuid_is_forbidden(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->addMember($user, $tenant);
        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Tenant-Id' => 'not-a-uuid'])->getJson('/api/me');

        $response->assertForbidden();
    }

    public function test_api_without_header_resolves_first_active_membership_with_no_session(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $this->addMember($user, $tenant);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertOk();
        $response->assertJsonPath('activeTenantId', $tenant->id);
    }
}
