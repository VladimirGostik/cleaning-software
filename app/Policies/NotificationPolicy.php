<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Notification;
use App\Models\User;

final class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewNotifications->value);
    }

    public function update(User $user, Notification $notification): bool
    {
        return $user->can(PermissionEnum::ViewNotifications->value)
            && $notification->isOwnedBy($user)
            && $notification->tenant_id === current_tenant_id();
    }
}
