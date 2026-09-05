<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CSRF verification is not meaningful in the test environment (array session driver
        // means each request gets a fresh session, so tokens never round-trip correctly).
        // Disable globally so web POST tests don't get spurious 419 responses.
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /**
     * Create and authenticate a user with the given role inside a tenant.
     * The user is also set as owner of the tenant (owner_id = user.id) so that
     * Tenant::owner resolves correctly.
     */
    protected function actingAsTenantUser(string $roleName, ?Tenant $tenant = null): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'locale' => 'sk',
        ]);

        if ($tenant === null) {
            // Create tenant with this user as owner
            $tenant = Tenant::factory()->forOwner($user)->create();
        }

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
