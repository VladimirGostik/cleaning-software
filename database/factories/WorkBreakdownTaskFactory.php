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
    /** @var class-string<WorkBreakdownTask> */
    protected $model = WorkBreakdownTask::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'work_breakdown_id' => WorkBreakdown::factory(),
            'name' => fake()->sentence(3),
            'description' => null,
            'frequency' => TaskFrequencyEnum::Weekly1x,
            'position' => 0,
        ];
    }

    public function oneTime(): static
    {
        return $this->state(fn () => ['frequency' => TaskFrequencyEnum::OneTime]);
    }

    public function frequency(TaskFrequencyEnum $frequency): static
    {
        return $this->state(fn () => ['frequency' => $frequency]);
    }
}
