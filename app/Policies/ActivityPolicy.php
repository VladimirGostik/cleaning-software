<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Activity;
use App\Models\User;

final class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewAuditLogs->value);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can(PermissionEnum::ViewAuditLogs->value)
            && $activity->isVisibleInTenant(current_tenant_id());
    }
}
