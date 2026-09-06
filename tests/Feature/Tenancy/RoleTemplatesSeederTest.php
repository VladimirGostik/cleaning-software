<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class RoleTemplatesSeederTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_ROLES = [
        RoleTemplatesSeeder::ADMIN_ROLE,
        'Vedúca',
        'Interná upratovačka',
        'Sekretárka',
        'Účtovníčka',
        'Zákazník',
    ];

    public function test_seeds_exactly_six_roles_per_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        RoleTemplatesSeeder::seedForTenant($tenant);

        $names = Role::inTenant($tenant->id)->pluck('name')->sort()->values()->toArray();
        $expected = collect(self::EXPECTED_ROLES)->sort()->values()->toArray();

        $this->assertSame($expected, $names);
    }

    public function test_admin_role_holds_every_permission(): void
    {
        $tenant = Tenant::factory()->create();

        RoleTemplatesSeeder::seedForTenant($tenant);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $admin = Role::inTenant($tenant->id)->where('name', RoleTemplatesSeeder::ADMIN_ROLE)->firstOrFail();

        $this->assertSame(count(PermissionEnum::cases()), $admin->permissions()->count());
    }

    public function test_each_non_admin_bundle_matches_exactly(): void
    {
        $tenant = Tenant::factory()->create();
        RoleTemplatesSeeder::seedForTenant($tenant);

        foreach (RoleTemplatesSeeder::templates() as $roleName => $permissions) {
            if ($roleName === RoleTemplatesSeeder::ADMIN_ROLE) {
                continue;
            }

            $role = Role::inTenant($tenant->id)->where('name', $roleName)->firstOrFail();
            $actual = $role->permissions()->pluck('name')->sort()->values()->toArray();
            $expected = collect($permissions)->sort()->values()->toArray();

            $this->assertSame($expected, $actual, "Bundle mismatch for role {$roleName}");
        }
    }

    public function test_seeding_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();

        RoleTemplatesSeeder::seedForTenant($tenant);
        RoleTemplatesSeeder::seedForTenant($tenant);

        $this->assertSame(6, Role::inTenant($tenant->id)->count());
    }

    public function test_migrate_fresh_seed_gives_admin_the_admin_role_in_its_tenant(): void
    {
        $this->artisan('app:demo')->assertSuccessful();

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $tenant = Tenant::where('owner_id', $admin->id)->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $this->assertTrue($admin->hasRole(RoleTemplatesSeeder::ADMIN_ROLE));
    }

    public function test_seeding_creates_permission_catalogue_when_missing(): void
    {
        $this->assertSame(0, Permission::count());

        $tenant = Tenant::factory()->create();
        RoleTemplatesSeeder::seedForTenant($tenant);

        $this->assertSame(count(PermissionEnum::cases()), Permission::count());
    }
}
