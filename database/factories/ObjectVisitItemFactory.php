<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CleaningObject;
use App\Models\ObjectVisitItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObjectVisitItem>
 */
final class ObjectVisitItemFactory extends Factory
{
    /** @var class-string<ObjectVisitItem> */
    protected $model = ObjectVisitItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'cleaning_object_id' => CleaningObject::factory(),
            'name' => fake()->unique()->words(2, true),
            'price' => fake()->randomFloat(2, 1, 100),
            'position' => 0,
        ];
    }

    public function forObject(CleaningObject $object): static
    {
        return $this->state(fn () => ['cleaning_object_id' => $object->id, 'tenant_id' => $object->tenant_id]);
    }
}
