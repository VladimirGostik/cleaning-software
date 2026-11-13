<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Devices\DeviceRegisterData;
use App\Enums\DevicePlatformEnum;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

final readonly class DeviceTokenService
{
    public function __construct(private DatabaseManager $db) {}

    public function register(User $user, DeviceRegisterData $data, ?string $mobileSessionId = null): void
    {
        $this->db->transaction(function () use ($user, $data, $mobileSessionId): void {
            if ($mobileSessionId !== null) {
                DeviceToken::query()->where('mobile_session_id', $mobileSessionId)->where('token', '!=', $data->token)->delete();
            }

            DeviceToken::query()->upsert(
                [[
                    'id' => new DeviceToken()->newUniqueId(),
                    'user_id' => $user->id,
                    'mobile_session_id' => $mobileSessionId,
                    'token' => $data->token,
                    'platform' => $data->platform->value,
                    'app_version' => $data->app_version,
                    'last_seen_at' => now(),
                ]],
                ['token'],
                ['user_id', 'mobile_session_id', 'platform', 'app_version', 'last_seen_at'],
            );
        });
    }

    public function unregister(User $user, string $token): void
    {
        DeviceToken::query()->where('user_id', $user->id)->where('token', $token)->delete();
    }

    public function removeAllFor(User $user): void
    {
        DeviceToken::query()->where('user_id', $user->id)->delete();
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

    public function hasIosTokens(User $user): bool
    {
        return DeviceToken::query()->where('user_id', $user->id)->where('platform', DevicePlatformEnum::Ios)->exists();
    }

    /** @return list<string> */
    public function iosTokensFor(User $user): array
    {
        /** @var list<string> $tokens */
        $tokens = DeviceToken::query()->where('user_id', $user->id)->where('platform', DevicePlatformEnum::Ios)->pluck('token')->values()->all();

        return $tokens;
    }

    /** @return list<string> */
    public function tokensFor(User $user): array
    {
        /** @var list<string> $tokens */
        $tokens = DeviceToken::query()->where('user_id', $user->id)->pluck('token')->values()->all();

        return $tokens;
    }
}
