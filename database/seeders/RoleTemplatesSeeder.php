<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates default role templates per spec — one set per tenant.
 *
 * Vlastník        — all permissions
 * Vedúca         — operational team management
 * Upratovačka    — minimal field worker
 * Sekretárka     — admin support, no finance/employees
 * Účtovníčka     — finance only
 * Zákazník       — customer portal
 */
final class RoleTemplatesSeeder extends Seeder
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function templates(): array
    {
        return [
            'Vlastník' => [], // empty = all permissions assigned at runtime
            'Vedúca' => [
                'view schedule', 'create schedule', 'edit schedule', 'assign cleaners',
                'view employees', 'assign employees',
                'view complaints', 'resolve complaints', 'reject complaints',
                'view photos', 'review photos',
                'view objects',
            ],
            'Upratovačka' => [
                'view schedule',
            ],
            'Sekretárka' => [
                'view clients', 'create clients', 'edit clients', 'delete clients',
                'view objects', 'create objects', 'edit objects', 'delete objects',
                'view quotes', 'create quotes', 'edit quotes', 'send quotes',
                'view contracts', 'create contracts',
                'view templates', 'upload templates', 'delete templates',
                'view notifications',
            ],
            'Účtovníčka' => [
                'view invoices', 'create invoices', 'edit invoices', 'cancel invoices',
                'view contracts',
                'view clients',
                'manage billing settings',
            ],
            'Zákazník' => [
                'view schedule',
                'view photos',
                'view complaints',
            ],
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissions = Permission::all();

        Tenant::query()->each(function (Tenant $tenant) use ($allPermissions): void {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

            foreach (self::templates() as $roleName => $permissions) {
                /** @var Role $role */
                $role = Role::findOrCreate($roleName, 'web');

                if ($roleName === 'Vlastník') {
                    $role->syncPermissions($allPermissions);

                    continue;
                }

                $role->syncPermissions($permissions);
            }
        });
    }
}
