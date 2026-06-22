<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewNotifications->value);
    }

    public function update(User $user, DatabaseNotification $notification): bool
    {
        return $user->can(PermissionEnum::ViewNotifications->value)
            && $notification->notifiable_id === $user->id
            && $notification->tenant_id === app('current_tenant_id');
    }
}
