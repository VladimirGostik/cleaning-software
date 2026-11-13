<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ScheduledJob;
use App\Models\ScheduledJobItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledJobItem>
 */
final class ScheduledJobItemFactory extends Factory
{
    /** @var class-string<ScheduledJobItem> */
    protected $model = ScheduledJobItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'cleaning_job_id' => ScheduledJob::factory(),
            'object_visit_item_id' => null,
            'name' => fake()->unique()->words(2, true),
            'unit_price' => fake()->randomFloat(2, 1, 100),
        ];
    }

    public function forJob(ScheduledJob $job): static
    {
        return $this->state(fn () => ['cleaning_job_id' => $job->id, 'tenant_id' => $job->tenant_id]);
    }
}
