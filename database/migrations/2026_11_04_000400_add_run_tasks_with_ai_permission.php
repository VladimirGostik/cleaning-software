<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const array ROLES = ['Admin', 'Vedúca'];

    public function up(): void
    {
        if (! DB::table('roles')->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $permissionId = DB::table('permissions')
                ->where('name', PermissionEnum::RunTasksWithAi->value)
                ->where('guard_name', 'web')
                ->value('id');

            if ($permissionId === null) {
                $permissionId = (string) Str::uuid7();
                DB::table('permissions')->insert([
                    'id' => $permissionId,
                    'name' => PermissionEnum::RunTasksWithAi->value,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (DB::table('roles')->whereIn('name', self::ROLES)->where('guard_name', 'web')->pluck('id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', PermissionEnum::RunTasksWithAi->value)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
