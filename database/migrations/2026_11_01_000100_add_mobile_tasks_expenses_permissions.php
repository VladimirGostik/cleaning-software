<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @return array<string, list<PermissionEnum>> */
    private function grants(): array
    {
        $all = [
            PermissionEnum::AccessMobileApp, PermissionEnum::ViewTasks, PermissionEnum::CreateTasks,
            PermissionEnum::EditTasks, PermissionEnum::DeleteTasks, PermissionEnum::ViewAllTasks,
            PermissionEnum::ViewExpenses, PermissionEnum::ManageExpenses,
        ];

        return [
            'Admin' => $all,
            'Vedúca' => [
                PermissionEnum::AccessMobileApp, PermissionEnum::ViewTasks, PermissionEnum::CreateTasks,
                PermissionEnum::EditTasks, PermissionEnum::ViewAllTasks,
            ],
            'Sekretárka' => [PermissionEnum::ViewTasks, PermissionEnum::CreateTasks, PermissionEnum::EditTasks],
            'Účtovníčka' => [
                PermissionEnum::ViewTasks, PermissionEnum::CreateTasks, PermissionEnum::EditTasks,
                PermissionEnum::ViewExpenses, PermissionEnum::ManageExpenses,
            ],
        ];
    }

    public function up(): void
    {
        if (! DB::table('roles')->exists()) {
            return;
        }

        $newPermissions = [
            PermissionEnum::AccessMobileApp, PermissionEnum::ViewTasks, PermissionEnum::CreateTasks,
            PermissionEnum::EditTasks, PermissionEnum::DeleteTasks, PermissionEnum::ViewAllTasks,
            PermissionEnum::ViewExpenses, PermissionEnum::ManageExpenses,
        ];

        DB::transaction(function () use ($newPermissions): void {
            /** @var array<string, string> $ids */
            $ids = [];

            foreach ($newPermissions as $permission) {
                $existing = DB::table('permissions')
                    ->where('name', $permission->value)
                    ->where('guard_name', 'web')
                    ->value('id');

                if ($existing === null) {
                    $existing = (string) Str::uuid7();
                    DB::table('permissions')->insert([
                        'id' => $existing,
                        'name' => $permission->value,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $ids[$permission->value] = $existing;
            }

            foreach ($this->grants() as $roleName => $permissions) {
                $roleIds = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->pluck('id');

                foreach ($roleIds as $roleId) {
                    foreach ($permissions as $permission) {
                        DB::table('role_has_permissions')->insertOrIgnore([
                            'permission_id' => $ids[$permission->value],
                            'role_id' => $roleId,
                        ]);
                    }
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $names = [
            PermissionEnum::AccessMobileApp->value, PermissionEnum::ViewTasks->value, PermissionEnum::CreateTasks->value,
            PermissionEnum::EditTasks->value, PermissionEnum::DeleteTasks->value, PermissionEnum::ViewAllTasks->value,
            PermissionEnum::ViewExpenses->value, PermissionEnum::ManageExpenses->value,
        ];

        DB::table('permissions')->whereIn('name', $names)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
