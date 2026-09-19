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
        return $user->can(PermissionEnum::ViewObjects->value) && $object->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateObjects->value);
    }

    public function update(User $user, CleaningObject $object): bool
    {
        return $user->can(PermissionEnum::EditObjects->value) && $object->isVisibleTo($user);
    }

    public function delete(User $user, CleaningObject $object): bool
    {
        return $user->can(PermissionEnum::DeleteObjects->value) && $object->isVisibleTo($user);
    }

    /**
     * Field-level gate (Q1) for object contact persons — reaching any call site already
     * required `viewAny` / `view`, which own visibility; this owns "may this actor see contacts
     * at all", keyed on the `ViewAllObjects` breadth modifier. No model argument — an own-only
     * actor never sees contacts on ANY object, so no per-row query is needed.
     */
    public function viewContacts(User $user): bool
    {
        return $user->can(PermissionEnum::ViewAllObjects->value);
    }
}
