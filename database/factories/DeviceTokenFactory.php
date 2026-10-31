<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DevicePlatformEnum;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceToken>
 */
final class DeviceTokenFactory extends Factory
{
    /** @var class-string<DeviceToken> */
    protected $model = DeviceToken::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => fake()->unique()->regexify('[A-Za-z0-9_-]{140}'),
            'platform' => DevicePlatformEnum::Ios,
            'app_version' => '1.0.0',
            'last_seen_at' => now(),
        ];
    }

    public function android(): self
    {
        return $this->state(['platform' => DevicePlatformEnum::Android]);
    }
}
