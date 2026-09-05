<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class TemporaryUploadPolicy
{
    public function create(User $user): bool
    {
        return $user->can('upload files');
    }

    public function delete(User $user): bool
    {
        return $user->can('upload files');
    }
}
