<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_user_id_active_tenant_and_permissions(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenantA->id, 'is_active' => true, 'joined_at' => now()]);
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenantB->id, 'is_active' => true, 'joined_at' => now()]);

        Permission::firstOrCreate(['name' => PermissionEnum::ViewClients->value, 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantA->id);
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'web', 'tenant_id' => $tenantA->id]);
        $role->syncPermissions([PermissionEnum::ViewClients->value]);
        $user->assignRole($role);

        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Tenant-Id' => $tenantA->id])->getJson('/api/me');

        $response->assertOk();
        $response->assertJsonPath('userId', $user->id);
        $response->assertJsonPath('activeTenantId', $tenantA->id);
        $response->assertJsonPath('permissions', [PermissionEnum::ViewClients->value]);
    }

    public function test_header_switch_changes_the_result(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenantA->id, 'is_active' => true, 'joined_at' => now()]);
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenantB->id, 'is_active' => true, 'joined_at' => now()]);
        Sanctum::actingAs($user);

        $responseA = $this->withHeaders(['X-Tenant-Id' => $tenantA->id])->getJson('/api/me');
        $responseB = $this->withHeaders(['X-Tenant-Id' => $tenantB->id])->getJson('/api/me');

        $responseA->assertJsonPath('activeTenantId', $tenantA->id);
        $responseB->assertJsonPath('activeTenantId', $tenantB->id);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertUnauthorized();
    }
}
