<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantMembership>
 */
final class TenantMembershipFactory extends Factory
{
    /** @var class-string<TenantMembership> */
    protected $model = TenantMembership::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'is_active' => true,
            'joined_at' => now(),
            'first_name' => null,
            'last_name' => null,
            'phone' => null,
            'position' => null,
        ];
    }

    public function withProfile(): static
    {
        return $this->state([
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'position' => fake()->jobTitle(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
        ]);
    }
}
