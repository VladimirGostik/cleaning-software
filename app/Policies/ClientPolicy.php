<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Client;
use App\Models\User;

final class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewClients->value);
    }

    public function view(User $user, Client $client): bool
    {
        return $user->can(PermissionEnum::ViewClients->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateClients->value);
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can(PermissionEnum::EditClients->value);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->can(PermissionEnum::DeleteClients->value);
    }
}
