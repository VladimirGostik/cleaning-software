<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CreateUserData;
use App\Data\UpdateUserData;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UserService
{
    public function create(CreateUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            /** @var User $user */
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
                'is_active' => $data->is_active,
            ]);

            if (! empty($data->roles)) {
                $user->syncRoles($data->roles);
            }

            return $user->fresh(['roles']);
        });
    }

    public function update(User $user, UpdateUserData $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->update([
                'name' => $data->name,
                'email' => $data->email,
                'is_active' => $data->is_active,
            ]);

            $user->syncRoles($data->roles);

            return $user->fresh(['roles']);
        });
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->syncRoles([]);
            $user->delete();
        });
    }
}
