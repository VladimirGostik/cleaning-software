<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ObjectTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CleaningObject>
 */
final class CleaningObjectFactory extends Factory
{
    /** @var class-string<CleaningObject> */
    protected $model = CleaningObject::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'type' => fake()->randomElement(ObjectTypeEnum::cases()),
            'name' => fake()->company().' - '.fake()->streetName(),
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'country' => 'SK',
            'access_code' => null,
            'key_box_code' => null,
            'key_count' => null,
            'special_instructions' => null,
            'area_sqm' => fake()->randomFloat(2, 20, 500),
            'floor' => null,
            'is_active' => true,
            'gps_lat' => null,
            'gps_lng' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
