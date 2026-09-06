<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\TenantMembership;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

trait CreatesUsers
{
    /**
     * Creates a user with a fresh tenant, active membership, and a tenant-scoped role
     * holding exactly the given ad-hoc permissions. Binds the tenant into the container
     * and session so a subsequent `actingAs($user)` HTTP request resolves it via
     * `TenantContextMiddleware`.
     */
    protected function userWithPermission(string ...$permissions): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($user)->create();
        TenantInterface::factory()->create(['tenant_id' => $tenant->id]);
        TenantMembership::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $role = Role::create(['name' => 'role_'.uniqid(), 'guard_name' => 'web']);
        $role->syncPermissions($permissions);
        $user->assignRole($role);

        $this->bindTenant($tenant);

        return $user;
    }

    /** Admin (all-permissions) user in a fresh tenant — thin wrapper over `actingAsTenantUser`. */
    protected function adminUser(): User
    {
        return $this->actingAsTenantUser();
    }
}
