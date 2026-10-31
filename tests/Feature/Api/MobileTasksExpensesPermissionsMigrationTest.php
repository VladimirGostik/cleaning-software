<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\PermissionEnum;
use App\Models\Role;
use App\Models\Tenant;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MobileTasksExpensesPermissionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const array NEW_PERMISSIONS = [
        'access mobile app', 'view tasks', 'create tasks', 'edit tasks', 'delete tasks',
        'view all tasks', 'view expenses', 'manage expenses',
    ];

    private function runMigration(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_11_01_000100_add_mobile_tasks_expenses_permissions.php');
        $migration->up(); // @phpstan-ignore method.notFound
    }

    /** @return list<string> */
    private function permissionsOf(Role $role): array
    {
        /** @var list<string> $names */
        $names = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_id', $role->id)
            ->whereIn('permissions.name', self::NEW_PERMISSIONS)
            ->pluck('permissions.name')
            ->sort()
            ->values()
            ->all();

        return $names;
    }

    private function seedLegacyTenant(): Tenant
    {
        $tenant = Tenant::factory()->create();
        RoleTemplatesSeeder::seedForTenant($tenant);
        DB::table('permissions')->whereIn('name', self::NEW_PERMISSIONS)->delete();

        return $tenant;
    }

    public function test_up_attaches_permissions_to_existing_roles_by_template_name_and_is_idempotent(): void
    {
        $tenant = $this->seedLegacyTenant();
        $custom = Role::query()->create(['name' => 'Custom', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $this->runMigration();
        $this->runMigration();

        $role = fn (string $name): Role => Role::query()->where('tenant_id', $tenant->id)->where('name', $name)->firstOrFail();

        $this->assertSame(8, DB::table('permissions')->whereIn('name', self::NEW_PERMISSIONS)->count());
        $this->assertCount(8, $this->permissionsOf($role('Admin')));
        $this->assertSame(
            ['access mobile app', 'create tasks', 'edit tasks', 'view all tasks', 'view tasks'],
            $this->permissionsOf($role('Vedúca')),
        );
        $this->assertSame(['create tasks', 'edit tasks', 'view tasks'], $this->permissionsOf($role('Sekretárka')));
        $this->assertSame(
            ['create tasks', 'edit tasks', 'manage expenses', 'view expenses', 'view tasks'],
            $this->permissionsOf($role('Účtovníčka')),
        );
        $this->assertSame([], $this->permissionsOf($role('Interná upratovačka')));
        $this->assertSame([], $this->permissionsOf($custom));
        $this->assertContains(PermissionEnum::AccessMobileApp->value, $this->permissionsOf($role('Vedúca')));
    }

    public function test_up_is_a_noop_without_roles(): void
    {
        DB::table('permissions')->whereIn('name', self::NEW_PERMISSIONS)->delete();

        $this->runMigration();

        $this->assertSame(0, DB::table('permissions')->whereIn('name', self::NEW_PERMISSIONS)->count());
    }
}
