<?php

declare(strict_types=1);

namespace Tests\Feature\Roles;

use App\Models\Role;
use App\Models\Tenant;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RecordOwnWorkPermissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const array NEW = ['record own work', 'access mobile app'];

    private function runMigration(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_11_23_000100_add_record_own_work_permission.php');
        $migration->up(); // @phpstan-ignore method.notFound
    }

    /** @return list<string> */
    private function grantedTo(Role $role): array
    {
        /** @var list<string> $names */
        $names = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_id', $role->id)
            ->whereIn('permissions.name', self::NEW)
            ->pluck('permissions.name')
            ->sort()
            ->values()
            ->all();

        return $names;
    }

    private function legacyTenant(): Tenant
    {
        $tenant = Tenant::factory()->create();
        RoleTemplatesSeeder::seedForTenant($tenant);
        DB::table('permissions')->where('name', 'record own work')->delete();
        DB::table('role_has_permissions')
            ->whereIn('role_id', Role::query()->where('tenant_id', $tenant->id)->where('name', 'Interná upratovačka')->pluck('id'))
            ->delete();

        return $tenant;
    }

    public function test_up_grants_by_role_name_leaves_renamed_roles_and_is_idempotent(): void
    {
        $tenant = $this->legacyTenant();
        $renamed = Role::query()->create(['name' => 'Moja upratovačka', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $role = fn (string $name): Role => Role::query()->where('tenant_id', $tenant->id)->where('name', $name)->firstOrFail();

        $this->runMigration();
        $this->runMigration();

        $this->assertSame(1, DB::table('permissions')->where('name', 'record own work')->count());
        $this->assertSame(['access mobile app', 'record own work'], $this->grantedTo($role('Interná upratovačka')));
        $this->assertContains('record own work', $this->grantedTo($role('Admin')));
        $this->assertSame(['access mobile app', 'record own work'], $this->grantedTo($role('Vedúca')));
        $this->assertSame([], $this->grantedTo($renamed));
        $this->assertSame(
            1,
            DB::table('role_has_permissions')->where('role_id', $role('Interná upratovačka')->id)
                ->whereIn('permission_id', DB::table('permissions')->where('name', 'record own work')->pluck('id'))->count(),
        );
    }

    public function test_up_is_a_noop_without_roles(): void
    {
        DB::table('permissions')->where('name', 'record own work')->delete();

        $this->runMigration();

        $this->assertSame(0, DB::table('permissions')->where('name', 'record own work')->count());
    }
}
