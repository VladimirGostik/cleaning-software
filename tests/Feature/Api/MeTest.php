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
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class MeTest extends TestCase
{
    use CreatesTaskFixtures, RefreshDatabase;

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

        $this->actingAsMobile($user);

        $response = $this->withHeaders(['X-Tenant-Id' => $tenantA->id])->getJson('/api/me');

        $response->assertOk();
        $response->assertJsonPath('userId', $user->id);
        $response->assertJsonPath('activeTenantId', $tenantA->id);
        $this->assertEqualsCanonicalizing(
            [PermissionEnum::ViewClients->value, PermissionEnum::AccessMobileApp->value],
            $response->json('permissions'),
        );
    }

    public function test_header_switch_changes_the_result(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenantA->id, 'is_active' => true, 'joined_at' => now()]);
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenantB->id, 'is_active' => true, 'joined_at' => now()]);
        $this->actingAsMobile($user);

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

    /** @return array<string, array{list<string>, string}> */
    public static function modeProvider(): array
    {
        return [
            'cleaner in one firm' => [['Interná upratovačka'], 'cleaner'],
            'cleaner in two firms' => [['Interná upratovačka', 'Interná upratovačka'], 'cleaner'],
            'admin' => [['Admin'], 'admin'],
            'vedúca' => [['Vedúca'], 'admin'],
            'admin in one firm, cleaner in another' => [['Admin', 'Interná upratovačka'], 'admin'],
            'no record own work' => [['Účtovníčka'], 'admin'],
        ];
    }

    /** @param list<string> $roles */
    #[DataProvider('modeProvider')]
    public function test_mode_comes_from_the_firms_with_mobile_access(array $roles, string $expected): void
    {
        $this->seedPermissionCatalog();
        $user = User::factory()->create();
        $first = null;

        foreach ($roles as $index => $role) {
            $firm = $this->firm('Firm '.$index);
            $first ??= $firm;
            $this->member($firm, $role, $user);
        }

        $this->assertNotNull($first);
        $this->apiAs($user, $first)->getJson('/api/me')->assertOk()->assertJsonPath('mode', $expected);
    }

    public function test_firm_without_mobile_access_does_not_count_for_the_mode(): void
    {
        $this->seedPermissionCatalog();
        $user = User::factory()->create();
        $cleaning = $this->firm('Cleaning');
        $admin = $this->firm('Admin');
        $this->member($cleaning, 'Interná upratovačka', $user);
        $this->member($admin, 'Admin', $user);
        $this->actingAsMobile($user);
        app(PermissionRegistrar::class)->setPermissionsTeamId($admin->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->revokePermissionTo(PermissionEnum::AccessMobileApp->value);
        $user->roles()->detach();
        $user->unsetRelation('roles')->unsetRelation('permissions');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->withHeaders(['X-Tenant-Id' => $cleaning->id])->getJson('/api/me')->assertOk()->assertJsonPath('mode', 'cleaner');
    }
}
