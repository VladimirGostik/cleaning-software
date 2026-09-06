<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\User;

final class TemporaryUploadPolicy
{
    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::UploadFiles->value);
    }

    public function delete(User $user): bool
    {
        return $user->can(PermissionEnum::UploadFiles->value);
    }
}
