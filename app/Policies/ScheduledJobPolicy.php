<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\ScheduledJob;
use App\Models\User;

final class ScheduledJobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewSchedule->value);
    }

    public function view(User $user, ScheduledJob $job): bool
    {
        return $user->can(PermissionEnum::ViewSchedule->value) && $job->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateSchedule->value);
    }

    public function update(User $user, ScheduledJob $job): bool
    {
        return $user->can(PermissionEnum::EditSchedule->value) && $job->isEditable() && $job->isVisibleTo($user);
    }

    public function assign(User $user, ScheduledJob $job): bool
    {
        return $user->can(PermissionEnum::AssignCleaners->value) && $job->canBeAssigned() && $job->isVisibleTo($user);
    }

    public function cancel(User $user, ScheduledJob $job): bool
    {
        return $user->can(PermissionEnum::EditSchedule->value) && $job->canBeCancelled() && $job->isVisibleTo($user);
    }
}
