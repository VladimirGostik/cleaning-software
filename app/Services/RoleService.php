<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\PermissionGroupData;
use App\Data\PermissionItemData;
use App\Enums\PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RoleTemplatesSeeder;
use InvalidArgumentException;

final readonly class RoleService
{
    public const array SYSTEM_ROLES = [RoleTemplatesSeeder::ADMIN_ROLE];

    /**
     * @param  array<int, string>  $permissions
     */
    public function create(string $name, array $permissions = []): Role
    {
        $tenantId = current_tenant_id();

        if (Role::inTenant($tenantId)->where('name', $name)->exists()) {
            throw new InvalidArgumentException(__('app.role_already_exists', ['name' => $name]));
        }

        /** @var Role $role */
        $role = Role::create(['name' => $name, 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        return $role->fresh(['permissions']);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function update(Role $role, string $name, array $permissions = []): Role
    {
        $tenantId = current_tenant_id();

        if ($role->name !== $name) {
            if (in_array($role->name, self::SYSTEM_ROLES, true)) {
                throw new InvalidArgumentException(__('app.role_cannot_rename_system', ['name' => $role->name]));
            }

            if (Role::inTenant($tenantId)->where('name', $name)->whereKeyNot($role->id)->exists()) {
                throw new InvalidArgumentException(__('app.role_already_exists', ['name' => $name]));
            }

            $role->name = $name;
            $role->save();
        }

        $role->syncPermissions($permissions);

        return $role->fresh(['permissions']);
    }

    public function delete(Role $role): void
    {
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            throw new InvalidArgumentException(__('app.role_cannot_delete_system', ['name' => $role->name]));
        }

        if ($role->users()->count() > 0) {
            throw new InvalidArgumentException(__('app.role_cannot_delete_assigned', ['name' => $role->name]));
        }

        $role->delete();
    }

    /** @return list<PermissionGroupData> */
    public function getPermissionsGrouped(): array
    {
        $permissionsByName = Permission::query()->get()->keyBy('name');

        $groups = [];

        foreach (PermissionEnum::cases() as $case) {
            $permission = $permissionsByName->get($case->value);

            if ($permission === null) {
                continue;
            }

            $groups[$case->group()]['group'] = $case->group();
            $groups[$case->group()]['group_label'] = $case->groupLabel();
            $groups[$case->group()]['permissions'][] = new PermissionItemData(
                id: (string) $permission->id,
                name: $case,
                label: $case->label(),
            );
        }

        return array_values(array_map(
            fn (array $group) => new PermissionGroupData(
                group: $group['group'],
                group_label: $group['group_label'],
                permissions: $group['permissions'],
            ),
            $groups,
        ));
    }
}
