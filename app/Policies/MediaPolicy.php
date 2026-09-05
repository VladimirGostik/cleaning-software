<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view media');
    }

    public function view(User $user, Media $media): bool
    {
        return $user->can('view media');
    }
}
