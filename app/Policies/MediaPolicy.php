<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Media;
use App\Models\User;

final class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewMedia->value);
    }

    public function view(User $user, Media $media): bool
    {
        return $user->can(PermissionEnum::ViewMedia->value) && $media->tenant_id === app('current_tenant_id');
    }
}
