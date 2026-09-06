<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    private bool $permissionsSeeded = false;

    /**
     * Creates (or reuses) a user + tenant, seeds the tenant's role templates, assigns
     * `$roleName`, logs the user in, and binds the tenant into container + session.
     */
    protected function actingAsTenantUser(
        string $roleName = RoleTemplatesSeeder::ADMIN_ROLE,
        ?Tenant $tenant = null,
        ?User $user = null,
    ): User {
        $this->seedPermissionsOnce();

        $user ??= User::factory()->create();
        $tenant ??= Tenant::factory()->forOwner($user)->create();

        TenantInterface::query()->firstOrCreate(['tenant_id' => $tenant->id]);
        TenantMembership::query()->firstOrCreate(
            ['user_id' => $user->id, 'tenant_id' => $tenant->id],
            ['is_active' => true, 'joined_at' => now()],
        );

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        RoleTemplatesSeeder::seedForTenant($tenant);

        /** @var Role $role */
        $role = Role::inTenant($tenant->id)->where('name', $roleName)->firstOrFail();
        $user->assignRole($role);

        $this->actingAs($user);
        $this->bindTenant($tenant);

        return $user;
    }

    /** Binds a tenant into the container + registrar only — no auth. For service/unit tests. */
    protected function bindTenant(Tenant $tenant): void
    {
        app()->instance('current_tenant_id', $tenant->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        if (app()->bound('session')) {
            session(['active_tenant_id' => $tenant->id]);
        }
    }

    private function seedPermissionsOnce(): void
    {
        if ($this->permissionsSeeded) {
            return;
        }

        $this->seed(PermissionSeeder::class);
        $this->permissionsSeeded = true;
    }
}
