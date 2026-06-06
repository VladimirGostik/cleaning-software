<?php

declare(strict_types=1);

namespace Database\Factories;

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
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (User $user): void {
            // is_super_admin is not fillable; set the safe default here so make() always has a typed bool.
            if (! isset($user->attributes['is_super_admin'])) {
                $user->is_super_admin = false;
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this
            ->afterMaking(function (User $user): void {
                $user->is_super_admin = true;
            })
            ->afterCreating(function (User $user): void {
                $user->is_super_admin = true;
                $user->saveQuietly();
            });
    }
}
