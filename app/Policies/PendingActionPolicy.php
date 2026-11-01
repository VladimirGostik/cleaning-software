<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PendingAction;
use App\Models\User;

final class PendingActionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasActiveMembership();
    }

    public function view(User $user, PendingAction $action): bool
    {
        return $action->user_id === $user->id;
    }

    public function confirm(User $user, PendingAction $action): bool
    {
        return $action->user_id === $user->id;
    }

    public function cancel(User $user, PendingAction $action): bool
    {
        return $action->user_id === $user->id;
    }
}
