<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Devices\DeviceRegisterData;
use App\Models\DeviceToken;
use App\Models\User;

final readonly class DeviceTokenService
{
    public function register(User $user, DeviceRegisterData $data, ?int $personalAccessTokenId = null): void
    {
        DeviceToken::query()->upsert(
            [[
                'id' => new DeviceToken()->newUniqueId(),
                'user_id' => $user->id,
                'personal_access_token_id' => $personalAccessTokenId,
                'token' => $data->token,
                'platform' => $data->platform->value,
                'app_version' => $data->app_version,
                'last_seen_at' => now(),
            ]],
            ['token'],
            ['user_id', 'personal_access_token_id', 'platform', 'app_version', 'last_seen_at'],
        );
    }

    public function unregister(User $user, string $token): void
    {
        DeviceToken::query()->where('user_id', $user->id)->where('token', $token)->delete();
    }

    public function removeAllFor(User $user): void
    {
        DeviceToken::query()->where('user_id', $user->id)->delete();
    }

    /** Drops a deactivated user's devices once no active membership is left to receive pushes for. */
    public function removeAllIfNoActiveMembership(User $user): void
    {
        if (! $user->hasActiveMembership()) {
            $this->removeAllFor($user);
        }
    }

    /** @param list<string> $tokens */
    public function removeTokens(array $tokens): void
    {
        if ($tokens !== []) {
            DeviceToken::query()->whereIn('token', $tokens)->delete();
        }
    }

    public function hasTokens(User $user): bool
    {
        return DeviceToken::query()->where('user_id', $user->id)->exists();
    }

    /** @return list<string> */
    public function tokensFor(User $user): array
    {
        /** @var list<string> $tokens */
        $tokens = DeviceToken::query()->where('user_id', $user->id)->pluck('token')->values()->all();

        return $tokens;
    }
}
