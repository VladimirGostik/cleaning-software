<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class RoleAssignmentGuard
{
    /**
     * Actor may only assign roles whose permission set is a subset of the actor's own
     * permissions — prevents privilege escalation via role assignment.
     *
     * @param  iterable<Role>  $roles
     */
    public function assertAssignable(User $actor, iterable $roles): void
    {
        /** @var list<string> $actorPermissions */
        $actorPermissions = $actor->getAllPermissions()->pluck('name')->all();

        foreach ($roles as $role) {
            /** @var list<string> $rolePermissions */
            $rolePermissions = $role->permissions->pluck('name')->all();

            if (array_diff($rolePermissions, $actorPermissions) !== []) {
                throw ValidationException::withMessages([
                    'roles' => [__('app.role_not_assignable')],
                ]);
            }
        }
    }

    /**
     * Actor may only grant direct permission overrides that are a subset of her own
     * permissions — prevents privilege escalation via direct permission grants. Throws
     * (rather than main's silent intersect) so the operator sees the rejected attempt (D8).
     *
     * @param  list<string>  $permissionNames
     */
    public function assertPermissionsGrantable(User $actor, array $permissionNames): void
    {
        /** @var list<string> $actorPermissions */
        $actorPermissions = $actor->getAllPermissions()->pluck('name')->all();

        if (array_diff($permissionNames, $actorPermissions) !== []) {
            throw ValidationException::withMessages([
                'permissions' => [__('app.permission_not_grantable')],
            ]);
        }
    }
}
