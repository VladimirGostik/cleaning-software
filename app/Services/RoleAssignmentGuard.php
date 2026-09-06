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
}
