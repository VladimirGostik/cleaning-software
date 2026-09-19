<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ObjectContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObjectContact>
 */
final class ObjectContactFactory extends Factory
{
    /** @var class-string<ObjectContact> */
    protected $model = ObjectContact::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'position' => fake()->jobTitle(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
