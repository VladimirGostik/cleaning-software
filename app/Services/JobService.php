<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Schedule\JobAssignData;
use App\Data\Schedule\JobIndexFilterData;
use App\Data\Schedule\JobUpsertData;
use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Models\ScheduledJob;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\WorkBreakdown;
use Carbon\CarbonPeriod;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class JobService
{
    public function __construct(
        private DatabaseManager $db,
    ) {}

    /**
     * @return LengthAwarePaginator<ScheduledJob>
     */
    public function paginate(JobIndexFilterData $filter, User $actor): LengthAwarePaginator
    {
        return QueryBuilder::for(ScheduledJob::query()->visibleTo($actor))
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('cleaning_object_id'),
                AllowedFilter::exact('assigned_membership_id'),
                AllowedFilter::callback('date_from', fn (Builder $q, string $v) => $q->whereDate('scheduled_date', '>=', $v)),
                AllowedFilter::callback('date_to', fn (Builder $q, string $v) => $q->whereDate('scheduled_date', '<=', $v)),
            )
            ->allowedSorts(
                AllowedSort::field('scheduled_date'),
                AllowedSort::field('status'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('scheduled_date')
            ->with([
                'cleaningObject.client:id,name',
                'assignedMembership.user:id,name,email',
            ])
            ->paginate($filter->per_page)
            ->appends(request()->query());
    }

    public function create(JobUpsertData $data): ScheduledJob
    {
        return $this->db->transaction(function () use ($data): ScheduledJob {
            $status = $data->assigned_membership_id !== null
                ? JobStatusEnum::Planned
                : JobStatusEnum::Unassigned;

            $job = ScheduledJob::create([
                'cleaning_object_id' => $data->cleaning_object_id,
                'assigned_membership_id' => $data->assigned_membership_id,
                'contract_id' => $data->contract_id,
                'type' => $data->type,
                'status' => $status,
                'scheduled_date' => $data->scheduled_date,
                'start_time' => $data->start_time,
                'end_time' => $data->end_time,
                'note' => $data->note,
            ]);

            return $job->load(['cleaningObject.client', 'assignedMembership.user']);
        });
    }

    public function update(ScheduledJob $job, JobUpsertData $data): ScheduledJob
    {
        throw_unless($job->isEditable(), ValidationException::withMessages([
            'job' => [__('app.schedule.job_not_editable')],
        ]));

        return $this->db->transaction(function () use ($job, $data): ScheduledJob {
            $status = $data->assigned_membership_id !== null
                ? JobStatusEnum::Planned
                : JobStatusEnum::Unassigned;

            $job->update([
                'cleaning_object_id' => $data->cleaning_object_id,
                'assigned_membership_id' => $data->assigned_membership_id,
                'contract_id' => $data->contract_id,
                'type' => $data->type,
                'status' => $status,
                'scheduled_date' => $data->scheduled_date,
                'start_time' => $data->start_time,
                'end_time' => $data->end_time,
                'note' => $data->note,
            ]);

            return $job->refresh()->load(['cleaningObject.client', 'assignedMembership.user']);
        });
    }

    public function assign(ScheduledJob $job, JobAssignData $data): ScheduledJob
    {
        throw_unless($job->canBeAssigned(), ValidationException::withMessages([
            'job' => [__('app.schedule.job_cannot_assign')],
        ]));

        return $this->db->transaction(function () use ($job, $data): ScheduledJob {
            $newStatus = $data->assigned_membership_id !== null
                ? JobStatusEnum::Planned
                : JobStatusEnum::Unassigned;

            $job->update([
                'assigned_membership_id' => $data->assigned_membership_id,
                'status' => $newStatus,
            ]);

            return $job->refresh()->load(['assignedMembership.user']);
        });
    }

    public function cancel(ScheduledJob $job): ScheduledJob
    {
        throw_unless($job->canBeCancelled(), ValidationException::withMessages([
            'job' => [__('app.schedule.job_cannot_cancel')],
        ]));

        return $this->db->transaction(function () use ($job): ScheduledJob {
            $job->update([
                'status' => JobStatusEnum::Cancelled,
                'cancelled_at' => now(),
            ]);

            return $job->refresh();
        });
    }

    /** Phase-2-mobile stub — marks job as completed (called from mobile check-out). */
    public function complete(ScheduledJob $job): ScheduledJob
    {
        throw_unless(
            $job->status->canTransitionTo(JobStatusEnum::Completed),
            ValidationException::withMessages(['job' => [__('app.schedule.invalid_transition')]]),
        );

        return $this->db->transaction(function () use ($job): ScheduledJob {
            $job->update([
                'status' => JobStatusEnum::Completed,
                'completed_at' => now(),
            ]);

            return $job->refresh();
        });
    }

    /** Phase-2-mobile stub — marks job as unapproved. */
    public function unapprove(ScheduledJob $job): ScheduledJob
    {
        throw_unless(
            $job->status->canTransitionTo(JobStatusEnum::Unapproved),
            ValidationException::withMessages(['job' => [__('app.schedule.invalid_transition')]]),
        );

        return $this->db->transaction(function () use ($job): ScheduledJob {
            $job->update(['status' => JobStatusEnum::Unapproved]);

            return $job->refresh();
        });
    }

    /**
     * Unassign all future jobs for a deactivated membership (called by EmployeeService::deactivate).
     * Returns count of unassigned jobs.
     */
    public function unassignFutureForMembership(TenantMembership $membership): int
    {
        return ScheduledJob::where('assigned_membership_id', $membership->id)
            ->whereDate('scheduled_date', '>=', now()->toDateString())
            ->whereIn('status', [JobStatusEnum::Planned->value, JobStatusEnum::Unassigned->value])
            ->update([
                'assigned_membership_id' => null,
                'status' => JobStatusEnum::Unassigned->value,
            ]);
    }

    /**
     * Materialize Pravidelná jobs per task frequency within a rolling horizon for a WorkBreakdown.
     * Idempotent — skips dates that already have a job (relies on partial-unique index as final guard).
     * Returns count of newly created jobs.
     */
    public function generateForBreakdown(WorkBreakdown $breakdown, CarbonPeriod $period): int
    {
        $breakdown->loadMissing(['tasks', 'cleaningObject', 'contract']);

        if ($breakdown->cleaningObject === null || ! $breakdown->cleaningObject->is_active) {
            return 0;
        }

        $contractValidFrom = $breakdown->contract?->valid_from;
        $contractEndDate = $breakdown->contract?->end_date;

        $count = 0;

        foreach ($breakdown->tasks as $task) {
            $intervalDays = $task->frequency->intervalDays();

            // Determine the reference start for interval alignment.
            $referenceDate = $contractValidFrom ?? Carbon::instance($period->getStartDate());

            // Collect existing job dates for this task within the period to skip on pre-check.
            $existingDates = ScheduledJob::where('work_breakdown_task_id', $task->id)
                ->whereBetween('scheduled_date', [
                    Carbon::instance($period->getStartDate())->toDateString(),
                    Carbon::instance($period->getEndDate())->toDateString(),
                ])
                ->pluck('scheduled_date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->flip()
                ->all();

            if ($intervalDays === null) {
                // One-time: generate a single job on the period start date if not already present.
                $date = Carbon::instance($period->getStartDate());

                if ($this->isWithinContractWindow($date, $contractValidFrom, $contractEndDate)
                    && ! isset($existingDates[$date->toDateString()])
                ) {
                    $this->createRecurringJob($breakdown, $task->id, $date);
                    $count++;
                }

                continue;
            }

            foreach ($period as $periodDate) {
                $date = Carbon::instance($periodDate);

                if (! $this->isWithinContractWindow($date, $contractValidFrom, $contractEndDate)) {
                    continue;
                }

                // Only generate dates aligned to the interval from the reference point.
                $daysDiff = (int) $referenceDate->diffInDays($date, false);

                if ($daysDiff < 0 || $daysDiff % $intervalDays !== 0) {
                    continue;
                }

                if (isset($existingDates[$date->toDateString()])) {
                    continue;
                }

                $this->createRecurringJob($breakdown, $task->id, $date);
                $count++;
            }
        }

        return $count;
    }

    private function isWithinContractWindow(
        Carbon $date,
        ?Carbon $validFrom,
        ?Carbon $endDate,
    ): bool {
        if ($validFrom !== null && $date->lt($validFrom->startOfDay())) {
            return false;
        }

        if ($endDate !== null && $date->gt($endDate->endOfDay())) {
            return false;
        }

        return true;
    }

    private function createRecurringJob(WorkBreakdown $breakdown, string $taskId, Carbon $date): void
    {
        ScheduledJob::create([
            'cleaning_object_id' => $breakdown->cleaning_object_id,
            'work_breakdown_id' => $breakdown->id,
            'work_breakdown_task_id' => $taskId,
            'contract_id' => $breakdown->contract_id,
            'type' => JobTypeEnum::Regular,
            'status' => JobStatusEnum::Unassigned,
            'scheduled_date' => $date->toDateString(),
        ]);
    }
}
