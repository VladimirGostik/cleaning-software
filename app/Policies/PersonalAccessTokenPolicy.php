<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/** Only the owner's tokens whose sole ability is `mcp` are manageable here; `mobile` tokens are never touched by this surface. */
final class PersonalAccessTokenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasActiveMembership();
    }

    public function create(User $user): bool
    {
        return $user->hasActiveMembership();
    }

    public function delete(User $user, PersonalAccessToken $token): bool
    {
        return $token->tokenable_type === $user->getMorphClass()
            && $token->tokenable_id === $user->id
            && $token->abilities === ['mcp'];
    }
}
