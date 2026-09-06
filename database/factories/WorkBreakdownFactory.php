<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CleaningObject;
use App\Models\Contract;
use App\Models\WorkBreakdown;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkBreakdown>
 */
final class WorkBreakdownFactory extends Factory
{
    /** @var class-string<WorkBreakdown> */
    protected $model = WorkBreakdown::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'cleaning_object_id' => CleaningObject::factory(),
            'contract_id' => null,
            'source_quote_id' => null,
            'name' => fake()->words(3, true),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function forContract(Contract $contract): static
    {
        return $this->state(fn () => [
            'contract_id' => $contract->id,
            'cleaning_object_id' => $contract->contractable_id,
            'source_quote_id' => $contract->quote_id,
        ]);
    }
}
