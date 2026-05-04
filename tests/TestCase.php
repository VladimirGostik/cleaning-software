<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsTenantUser(string $roleName, ?Tenant $tenant = null): User
    {
        $tenant ??= Tenant::factory()->create();

        $user = User::factory()->create([
            'is_active' => true,
            'locale' => 'sk',
        ]);

        TenantMembership::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $this->seed(PermissionSeeder::class);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $this->seed(RoleTemplatesSeeder::class);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        /** @var Role $role */
        $role = Role::where('name', $roleName)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $user->assignRole($role);

        $this->actingAs($user);

        session(['active_tenant_id' => $tenant->id]);

        app()->instance('current_tenant_id', $tenant->id);

        return $user;
    }
}
