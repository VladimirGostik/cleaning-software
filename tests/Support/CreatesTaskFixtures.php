<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleTemplatesSeeder;
use Spatie\Permission\PermissionRegistrar;

trait CreatesTaskFixtures
{
    protected function seedPermissionCatalog(): void
    {
        $this->seed(PermissionSeeder::class);
    }

    protected function firm(string $name = 'Firm'): Tenant
    {
        $tenant = Tenant::factory()->create(['name' => $name]);
        TenantInterface::query()->firstOrCreate(['tenant_id' => $tenant->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        RoleTemplatesSeeder::seedForTenant($tenant);

        return $tenant;
    }

    protected function member(Tenant $tenant, string $role, ?User $user = null): User
    {
        $user ??= User::factory()->create();

        TenantMembership::query()->firstOrCreate(
            ['user_id' => $user->id, 'tenant_id' => $tenant->id],
            ['is_active' => true, 'joined_at' => now()],
        );

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->assignRole(Role::inTenant($tenant->id)->where('name', $role)->firstOrFail());

        return $user;
    }

    protected function membershipOf(User $user, Tenant $tenant): TenantMembership
    {
        return TenantMembership::query()->where('user_id', $user->id)->where('tenant_id', $tenant->id)->firstOrFail();
    }

    /** Authenticates without pre-binding the tenant, so the request resolves it like production. */
    protected function webAs(User $user, Tenant $tenant): static
    {
        $this->consoleContext();
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
    }

    protected function apiAs(User $user, Tenant $tenant): static
    {
        $this->app['auth']->forgetGuards();
        $this->actingAsMobile($user);

        return $this->withHeaders(['X-Tenant-Id' => $tenant->id]);
    }

    protected function consoleContext(): void
    {
        $this->app['auth']->forgetGuards();
        app()->forgetInstance('current_tenant_id');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }
}
