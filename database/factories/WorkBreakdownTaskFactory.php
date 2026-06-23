<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskFrequencyEnum;
use App\Models\WorkBreakdown;
use App\Models\WorkBreakdownTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkBreakdownTask>
 */
final class WorkBreakdownTaskFactory extends Factory
{
    protected $model = WorkBreakdownTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'work_breakdown_id' => WorkBreakdown::factory(),
            'name' => fake()->words(3, true),
            'description' => null,
            'frequency' => fake()->randomElement(TaskFrequencyEnum::cases())->value,
            'position' => 0,
        ];
    }

    public function weekly(): self
    {
        return $this->state(['frequency' => TaskFrequencyEnum::Weekly1x->value]);
    }

    public function oneTime(): self
    {
        return $this->state(['frequency' => TaskFrequencyEnum::OneTime->value]);
    }
}
