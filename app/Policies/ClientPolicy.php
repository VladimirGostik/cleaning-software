<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

final class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view clients');
    }

    public function view(User $user, Client $client): bool
    {
        return $user->can('view clients');
    }

    public function create(User $user): bool
    {
        return $user->can('create clients');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can('edit clients');
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->can('delete clients');
    }
}
