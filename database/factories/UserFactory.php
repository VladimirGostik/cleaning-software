<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionPlanEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'locale' => 'sk',
            'is_active' => true,
            'subscription_plan' => SubscriptionPlanEnum::Free->value,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function pro(): static
    {
        return $this->state(['subscription_plan' => SubscriptionPlanEnum::Pro->value]);
    }

    public function enterprise(): static
    {
        return $this->state(['subscription_plan' => SubscriptionPlanEnum::Enterprise->value]);
    }
}
