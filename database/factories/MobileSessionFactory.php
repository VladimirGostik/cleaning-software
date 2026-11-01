<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MobileSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MobileSession>
 */
final class MobileSessionFactory extends Factory
{
    /** @var class-string<MobileSession> */
    protected $model = MobileSession::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'refresh_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(365),
        ];
    }
}
