<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class PermissionSeeder extends Seeder
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function permissionGroups(): array
    {
        return [
            'clients' => ['view clients', 'create clients', 'edit clients', 'delete clients'],
            'objects' => ['view objects', 'create objects', 'edit objects', 'delete objects'],
            'quotes' => ['view quotes', 'create quotes', 'edit quotes', 'send quotes', 'approve quotes'],
            'contracts' => ['view contracts', 'create contracts', 'edit contracts', 'terminate contracts'],
            'employees' => ['view employees', 'create employees', 'edit employees', 'assign employees'],
            'schedule' => ['view schedule', 'create schedule', 'edit schedule', 'assign cleaners'],
            'invoices' => ['view invoices', 'create invoices', 'edit invoices', 'cancel invoices'],
            'templates' => ['view templates', 'upload templates', 'delete templates'],
            'complaints' => ['view complaints', 'resolve complaints', 'reject complaints'],
            'photos' => ['view photos', 'review photos'],
            'notifications' => ['view notifications', 'configure notifications'],
            'permissions' => ['manage roles'],
            'settings' => ['manage tenant', 'manage billing settings', 'manage subscription'],
            'tenants' => ['view tenants', 'create tenants', 'edit tenants'],
            'audit' => ['view audit logs'],
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::permissionGroups() as $permissions) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }
        }
    }
}
