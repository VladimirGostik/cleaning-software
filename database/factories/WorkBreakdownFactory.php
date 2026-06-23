<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CleaningObject;
use App\Models\WorkBreakdown;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkBreakdown>
 */
final class WorkBreakdownFactory extends Factory
{
    protected $model = WorkBreakdown::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'cleaning_object_id' => CleaningObject::factory(),
            'contract_id' => null,
            'source_quote_id' => null,
            'name' => fake()->words(3, true),
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
