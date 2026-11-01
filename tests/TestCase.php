<?php

declare(strict_types=1);

namespace Tests;

use App\Contracts\RendersContractPdf;
use App\Enums\PermissionEnum;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\PendingCommand;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeContractPdfRenderer;

abstract class TestCase extends BaseTestCase
{
    private bool $permissionsSeeded = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(RendersContractPdf::class, FakeContractPdfRenderer::class);
    }

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

    /** Sanctum `mobile` token for `$user`; grants `access mobile app` directly in every active membership tenant. */
    protected function actingAsMobile(User $user): User
    {
        $this->seedPermissionsOnce();
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        /** @var list<string> $tenantIds */
        $tenantIds = $user->memberships()->where('is_active', true)->pluck('tenant_id')->all();

        foreach ($tenantIds as $tenantId) {
            $registrar->setPermissionsTeamId($tenantId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->givePermissionTo(PermissionEnum::AccessMobileApp->value);
        }

        $registrar->setPermissionsTeamId($previous);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        Sanctum::actingAs($user, ['mobile']);

        return $user;
    }

    /**
     * `artisan()` is typed `PendingCommand|int` — it returns the int exit code once the
     * command has already run. Use this when chaining assertions onto the pending command.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function artisanCommand(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);

        $this->assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
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
