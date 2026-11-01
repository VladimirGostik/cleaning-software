<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\UpdateProfileData;
use App\Enums\MobileSessionRevokeReasonEnum;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

final readonly class ProfileService
{
    public function __construct(
        private DatabaseManager $db,
        private MobileSessionService $sessions,
    ) {}

    public function updateProfile(User $user, UpdateProfileData $data): User
    {
        $user->update([
            'name' => $data->name,
            'email' => $data->email,
            'locale' => $data->locale,
        ]);

        session()->put('locale', $data->locale);
        app()->setLocale($data->locale);

        return $user->refresh();
    }

    /** Pass the token-bearing user so the phone that made the change keeps its session; web has none, so every phone is logged out. */
    public function changePassword(User $user, string $password): void
    {
        $this->db->transaction(function () use ($user, $password): void {
            $current = $this->sessions->currentFor($user);

            $user->update(['password' => $password]);

            $this->sessions->revokeAllFor($user, MobileSessionRevokeReasonEnum::PasswordChanged, except: $current);
        });
    }
}
