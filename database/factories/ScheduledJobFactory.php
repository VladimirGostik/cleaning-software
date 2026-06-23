<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Models\CleaningObject;
use App\Models\ScheduledJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledJob>
 */
final class ScheduledJobFactory extends Factory
{
    protected $model = ScheduledJob::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'cleaning_object_id' => CleaningObject::factory(),
            'assigned_membership_id' => null,
            'work_breakdown_id' => null,
            'work_breakdown_task_id' => null,
            'contract_id' => null,
            'invoice_id' => null,
            'type' => JobTypeEnum::OneOff->value,
            'status' => JobStatusEnum::Unassigned->value,
            'scheduled_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'start_time' => null,
            'end_time' => null,
            'note' => null,
        ];
    }

    public function planned(): self
    {
        return $this->state(['status' => JobStatusEnum::Planned->value]);
    }

    public function completed(): self
    {
        return $this->state([
            'status' => JobStatusEnum::Completed->value,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): self
    {
        return $this->state([
            'status' => JobStatusEnum::Cancelled->value,
            'cancelled_at' => now(),
        ]);
    }

    public function regular(): self
    {
        return $this->state(['type' => JobTypeEnum::Regular->value]);
    }
}
