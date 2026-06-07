<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\User;

final class ObjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewObjects->value);
    }

    public function view(User $user, CleaningObject $object): bool
    {
        return $user->can(PermissionEnum::ViewObjects->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateObjects->value);
    }

    public function update(User $user, CleaningObject $object): bool
    {
        return $user->can(PermissionEnum::EditObjects->value);
    }

    public function delete(User $user, CleaningObject $object): bool
    {
        return $user->can(PermissionEnum::DeleteObjects->value);
    }
}
