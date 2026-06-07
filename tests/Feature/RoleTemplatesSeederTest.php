<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleTemplatesSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class RoleTemplatesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_for_tenant_creates_six_roles(): void
    {
        // Arrange
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();

        // Act
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        RoleTemplatesSeeder::seedForTenant($tenant);

        // Assert
        $roles = Role::where('tenant_id', $tenant->id)->pluck('name');
        $this->assertCount(6, $roles);

        $expectedRoles = ['Vlastník', 'Vedúca', 'Upratovačka', 'Sekretárka', 'Účtovníčka', 'Zákazník'];
        foreach ($expectedRoles as $roleName) {
            $this->assertContains($roleName, $roles, "Role '{$roleName}' should exist for tenant.");
        }
    }

    public function test_vlastnik_has_all_permissions(): void
    {
        // Arrange
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        // Act
        RoleTemplatesSeeder::seedForTenant($tenant);

        // Assert
        /** @var Role $vlastnik */
        $vlastnik = Role::where('name', 'Vlastník')->where('tenant_id', $tenant->id)->firstOrFail();
        $allPermissionCount = Permission::count();
        $this->assertSame($allPermissionCount, $vlastnik->permissions()->count());
    }

    public function test_template_roles_have_correct_permission_sets(): void
    {
        // Arrange
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        // Act
        RoleTemplatesSeeder::seedForTenant($tenant);

        // Assert — spot-check a few roles
        /** @var Role $upratovacka */
        $upratovacka = Role::where('name', 'Upratovačka')->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(1, $upratovacka->permissions()->count());
        $this->assertSame('view schedule', $upratovacka->permissions()->first()->name);

        /** @var Role $zakaznik */
        $zakaznik = Role::where('name', 'Zákazník')->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(3, $zakaznik->permissions()->count());
    }

    public function test_seed_for_tenant_is_idempotent(): void
    {
        // Arrange
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        // Act — run twice
        RoleTemplatesSeeder::seedForTenant($tenant);
        RoleTemplatesSeeder::seedForTenant($tenant);

        // Assert — still 6 roles, no duplicates
        $this->assertSame(6, Role::where('tenant_id', $tenant->id)->count());
    }

    public function test_migrate_fresh_seed_creates_demo_accounts_with_vlastnik_role(): void
    {
        // Arrange + Act
        $this->seed(PermissionSeeder::class);
        $this->seed(UserSeeder::class);

        // Assert — admin exists with Pro plan and owns 1 tenant (IČO 12345678)
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame(1, $admin->ownedTenants()->count());
        $this->assertSame(1, $admin->tenants()->count());

        $adminTenant = Tenant::where('ico', '12345678')->firstOrFail();
        $this->assertSame($admin->id, $adminTenant->owner_id);

        // Assert — 4 demo-tier accounts exist, each owns 1 tenant
        $demoEmails = ['free@demo.sk', 'starter@demo.sk', 'pro@demo.sk', 'enterprise@demo.sk'];
        foreach ($demoEmails as $email) {
            $demoUser = User::where('email', $email)->firstOrFail();
            $this->assertSame(1, $demoUser->ownedTenants()->count(), "User {$email} should own exactly 1 tenant.");

            // Assert Vlastník role on own tenant
            $tenant = $demoUser->ownedTenants()->firstOrFail();
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $ownerRole = Role::where('name', 'Vlastník')
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            $this->assertTrue(
                $demoUser->roles()->where('roles.id', $ownerRole->id)->exists(),
                "User {$email} should have Vlastník role on own tenant.",
            );
        }

        // Assert admin has Vlastník on own tenant
        app(PermissionRegistrar::class)->setPermissionsTeamId($adminTenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminOwnerRole = Role::where('name', 'Vlastník')
            ->where('tenant_id', $adminTenant->id)
            ->firstOrFail();

        $this->assertTrue(
            $admin->roles()->where('roles.id', $adminOwnerRole->id)->exists(),
            'Admin should have Vlastník role on Demo Cleaning s.r.o.',
        );
    }
}
