<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const array RENAMES = [
        'view contract_templates' => 'view document_templates',
        'create contract_templates' => 'create document_templates',
        'edit contract_templates' => 'edit document_templates',
        'delete contract_templates' => 'delete document_templates',
    ];

    private const array ADDED = [
        'view employee_personal_data',
        'view employee_documents',
        'manage employee_documents',
    ];

    private const string ACCOUNTANT_ROLE = 'Účtovníčka';

    public function up(): void
    {
        DB::table('activity_log')->where('subject_type', 'App\Models\Client')->update(['subject_type' => 'client']);

        // A fresh install has no permission catalogue yet; `PermissionSeeder` creates every case.
        if (! DB::table('permissions')->exists()) {
            return;
        }

        foreach (self::RENAMES as $old => $new) {
            DB::table('permissions')->where('name', $old)->where('guard_name', 'web')->update(['name' => $new]);
        }

        $now = now();

        foreach (self::ADDED as $name) {
            DB::table('permissions')->insertOrIgnore([
                'id' => (string) Str::uuid7(),
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->attach(self::ADDED, 'Admin');
        $this->attach(['view employee_personal_data'], self::ACCOUNTANT_ROLE);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('activity_log')->where('subject_type', 'client')->update(['subject_type' => 'App\Models\Client']);

        $addedIds = DB::table('permissions')->whereIn('name', self::ADDED)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $addedIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $addedIds)->delete();
        DB::table('permissions')->whereIn('id', $addedIds)->delete();

        foreach (self::RENAMES as $old => $new) {
            DB::table('permissions')->where('name', $new)->where('guard_name', 'web')->update(['name' => $old]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @param list<string> $permissions */
    private function attach(array $permissions, string $roleName): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', $permissions)->pluck('id');
        $roleIds = DB::table('roles')->where('name', $roleName)->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
    }
};
