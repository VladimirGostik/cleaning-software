<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\UpdateProfileData;
use App\Models\User;

final readonly class ProfileService
{
    public function updateProfile(User $user, UpdateProfileData $data): User
    {
        $user->update([
            'name' => $data->name,
            'email' => $data->email,
            'locale' => $data->locale,
        ]);

        session()->put('locale', $data->locale);
        app()->setLocale($data->locale);

        return $user->fresh();
    }
}
