<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use InvalidArgumentException;

final readonly class RoleService
{
    public const array SYSTEM_ROLES = ['admin'];

    /**
     * @param  array<int, string>  $permissions
     */
    public function create(string $name, array $permissions = []): Role
    {
        if (Role::where('name', $name)->exists()) {
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
        if ($role->name !== $name) {
            if (in_array($role->name, self::SYSTEM_ROLES, true)) {
                throw new InvalidArgumentException(__('app.role_cannot_rename_system', ['name' => $role->name]));
            }

            if (Role::where('name', $name)->where('id', '!=', $role->id)->exists()) {
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

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function getPermissionsGrouped(): array
    {
        return Permission::all()
            ->groupBy(fn (Permission $p) => explode(' ', $p->name)[1] ?? 'other')
            ->map(fn ($perms, string $group) => [
                'group' => $group,
                'permissions' => $perms->values()->toArray(),
            ])
            ->values()
            ->toArray();
    }
}
