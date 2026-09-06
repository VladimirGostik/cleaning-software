<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Models\CleaningObject;
use App\Models\ScheduledJob;
use App\Models\TenantMembership;
use App\Models\WorkBreakdownTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledJob>
 */
final class ScheduledJobFactory extends Factory
{
    /** @var class-string<ScheduledJob> */
    protected $model = ScheduledJob::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'cleaning_object_id' => CleaningObject::factory(),
            'assigned_membership_id' => null,
            'work_breakdown_id' => null,
            'work_breakdown_task_id' => null,
            'contract_id' => null,
            'invoice_id' => null,
            'type' => JobTypeEnum::OneOff,
            'status' => JobStatusEnum::Unassigned,
            'scheduled_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'start_time' => null,
            'end_time' => null,
            'note' => null,
            'gps_lat' => null,
            'gps_lng' => null,
            'completed_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function planned(): static
    {
        return $this->state(fn () => ['status' => JobStatusEnum::Planned]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => JobStatusEnum::InProgress]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => JobStatusEnum::Completed, 'completed_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => JobStatusEnum::Cancelled, 'cancelled_at' => now()]);
    }

    public function regular(): static
    {
        return $this->state(fn () => ['type' => JobTypeEnum::Regular]);
    }

    public function assignedTo(TenantMembership $membership): static
    {
        return $this->state(fn () => [
            'assigned_membership_id' => $membership->id,
            'status' => JobStatusEnum::Planned,
        ]);
    }

    public function forObject(CleaningObject $object): static
    {
        return $this->state(fn () => ['cleaning_object_id' => $object->id]);
    }

    public function fromTask(WorkBreakdownTask $task): static
    {
        return $this->state(function () use ($task): array {
            $breakdown = $task->workBreakdown()->firstOrFail();

            return [
                'work_breakdown_id' => $task->work_breakdown_id,
                'work_breakdown_task_id' => $task->id,
                'cleaning_object_id' => $breakdown->cleaning_object_id,
                'contract_id' => $breakdown->contract_id,
                'type' => JobTypeEnum::Regular,
            ];
        });
    }
}
