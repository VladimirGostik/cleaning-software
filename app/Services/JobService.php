<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Schedule\JobAssignData;
use App\Data\Schedule\JobCalendarFilterData;
use App\Data\Schedule\JobCalendarItemData;
use App\Data\Schedule\JobListItemData;
use App\Data\Schedule\JobStoreData;
use App\Data\Schedule\JobUpdateData;
use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\Contract;
use App\Models\ScheduledJob;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\WorkBreakdown;
use App\Models\WorkBreakdownTask;
use App\Scopes\TenantScope;
use App\Utils\AllowedFilter;
use App\Utils\Filters;
use Carbon\CarbonPeriod;
use DateTimeInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class JobService
{
    public function __construct(
        private DatabaseManager $db,
    ) {}

    /**
     * @return LengthAwarePaginator<int, JobListItemData>
     */
    public function paginate(Request $request, User $actor): LengthAwarePaginator
    {
        return QueryBuilder::for(ScheduledJob::query()->visibleTo($actor))
            ->allowedFilters(
                AllowedFilter::callbackClean('search', function (Builder $query, mixed $value): void {
                    if (blank($value) || ! is_scalar($value)) {
                        return;
                    }

                    $like = '%'.Filters::escapeLikeValue((string) $value).'%';
                    $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

                    $query->where(function (Builder $q) use ($like, $operator): void {
                        $q->where('note', $operator, $like)
                            ->orWhereHas('cleaningObject', fn (Builder $c) => $c->where('name', $operator, $like));
                    });
                }),
                AllowedFilter::dynamic('status'),
                AllowedFilter::dynamic('type'),
                AllowedFilter::dynamic('cleaning_object_id')->uuid(),
                AllowedFilter::dynamic('assigned_membership_id')->uuid(),
                AllowedFilter::dynamic('scheduled_date')->date(),
            )
            ->allowedSorts('scheduled_date', 'status', 'type', 'created_at')
            ->defaultSort('scheduled_date')
            ->with(['cleaningObject.client:id,name', 'assignedMembership.user:id,name,email'])
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (ScheduledJob $job) => JobListItemData::fromModel($job));
    }

    /** @return Collection<int, JobCalendarItemData> */
    public function calendar(JobCalendarFilterData $filter, User $actor): Collection
    {
        return ScheduledJob::query()
            ->visibleTo($actor)
            ->whereBetween('scheduled_date', [$filter->from, $filter->to])
            ->when($filter->cleaning_object_id !== null, fn (Builder $q) => $q->where('cleaning_object_id', $filter->cleaning_object_id))
            ->when($filter->assigned_membership_id !== null, fn (Builder $q) => $q->where('assigned_membership_id', $filter->assigned_membership_id))
            ->when($filter->status !== null, fn (Builder $q) => $q->where('status', $filter->status))
            ->with(['cleaningObject:id,name', 'assignedMembership.user:id,name,email'])
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (ScheduledJob $job) => JobCalendarItemData::fromModel($job));
    }

    public function create(JobStoreData $data, User $actor): ScheduledJob
    {
        if ($data->assigned_membership_id !== null && ! $actor->can(PermissionEnum::AssignCleaners->value)) {
            throw ValidationException::withMessages([
                'assigned_membership_id' => [__('app.job_assign_forbidden')],
            ]);
        }

        $object = CleaningObject::query()->findOrFail($data->cleaning_object_id);

        if (! $object->isVisibleTo($actor)) {
            throw ValidationException::withMessages([
                'cleaning_object_id' => [__('app.job_object_not_visible')],
            ]);
        }

        return $this->db->transaction(function () use ($data): ScheduledJob {
            $job = ScheduledJob::create([
                'cleaning_object_id' => $data->cleaning_object_id,
                'assigned_membership_id' => $data->assigned_membership_id,
                'type' => $data->type,
                'status' => $data->assigned_membership_id !== null ? JobStatusEnum::Planned : JobStatusEnum::Unassigned,
                'scheduled_date' => $data->scheduled_date,
                'start_time' => $data->start_time,
                'end_time' => $data->end_time,
                'note' => $data->note,
            ]);

            return $job->load(['cleaningObject.client', 'assignedMembership.user']);
        });
    }

    public function update(ScheduledJob $job, JobUpdateData $data, User $actor): ScheduledJob
    {
        if (! $job->isEditable()) {
            throw ValidationException::withMessages(['status' => [__('app.job_not_editable')]]);
        }

        $object = CleaningObject::query()->findOrFail($data->cleaning_object_id);

        if (! $object->isVisibleTo($actor)) {
            throw ValidationException::withMessages([
                'cleaning_object_id' => [__('app.job_object_not_visible')],
            ]);
        }

        return $this->db->transaction(function () use ($job, $data): ScheduledJob {
            $job->update([
                'cleaning_object_id' => $data->cleaning_object_id,
                'type' => $data->type,
                'scheduled_date' => $data->scheduled_date,
                'start_time' => $data->start_time,
                'end_time' => $data->end_time,
                'note' => $data->note,
            ]);

            return $job->load(['cleaningObject.client', 'assignedMembership.user']);
        });
    }

    public function assign(ScheduledJob $job, JobAssignData $data): ScheduledJob
    {
        if (! $job->canBeAssigned()) {
            throw ValidationException::withMessages(['status' => [__('app.job_cannot_assign')]]);
        }

        return $this->db->transaction(function () use ($job, $data): ScheduledJob {
            $job->update([
                'assigned_membership_id' => $data->assigned_membership_id,
                'status' => $data->assigned_membership_id !== null ? JobStatusEnum::Planned : JobStatusEnum::Unassigned,
            ]);

            return $job->load(['cleaningObject.client', 'assignedMembership.user']);
        });
    }

    public function cancel(ScheduledJob $job): ScheduledJob
    {
        if (! $job->canBeCancelled()) {
            throw ValidationException::withMessages(['status' => [__('app.job_cannot_cancel')]]);
        }

        return $this->db->transaction(function () use ($job): ScheduledJob {
            $job->update(['status' => JobStatusEnum::Cancelled, 'cancelled_at' => now()]);

            return $job;
        });
    }

    public function complete(ScheduledJob $job): ScheduledJob
    {
        if (! $job->status->canTransitionTo(JobStatusEnum::Completed)) {
            throw ValidationException::withMessages(['status' => [__('app.job_invalid_transition')]]);
        }

        return $this->db->transaction(function () use ($job): ScheduledJob {
            $job->update(['status' => JobStatusEnum::Completed, 'completed_at' => now()]);

            return $job;
        });
    }

    public function unapprove(ScheduledJob $job): ScheduledJob
    {
        if (! $job->status->canTransitionTo(JobStatusEnum::Unapproved)) {
            throw ValidationException::withMessages(['status' => [__('app.job_invalid_transition')]]);
        }

        return $this->db->transaction(function () use ($job): ScheduledJob {
            $job->update(['status' => JobStatusEnum::Unapproved]);

            return $job;
        });
    }

    /**
     * Unassigns every future `Planned` job pointing at this membership. Scope-free — called both
     * from HTTP (`EmployeeService::deactivate`) and from the API `UserService` (mobile identity
     * path, no HTTP tenant binding guaranteed).
     */
    public function unassignFutureForMembership(TenantMembership $membership): int
    {
        return ScheduledJob::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $membership->tenant_id)
            ->where('assigned_membership_id', $membership->id)
            ->whereDate('scheduled_date', '>=', today())
            ->where('status', JobStatusEnum::Planned)
            ->update(['assigned_membership_id' => null, 'status' => JobStatusEnum::Unassigned->value]);
    }

    /**
     * Materialises `ScheduledJob` rows for every task of `$breakdown` across `$period`. Idempotent
     * — relies on `cleaning_jobs_recurrence_unique` as the last-resort guard (never caught here; a
     * violation means a bug in the "not existing" pre-check below).
     */
    public function generateForBreakdown(WorkBreakdown $breakdown, CarbonPeriod $period): int
    {
        $object = $breakdown->cleaningObject;

        if ($object === null || ! $object->is_active || $object->trashed()) {
            return 0;
        }

        $contract = $breakdown->contract;
        $periodStart = Carbon::instance($period->getStartDate());
        $periodEnd = Carbon::instance($period->getEndDate() ?? $period->getStartDate());
        $referenceDate = $contract !== null ? $contract->valid_from : $periodStart;

        $created = 0;

        /** @var WorkBreakdownTask $task */
        foreach ($breakdown->tasks as $task) {
            $existingDates = ScheduledJob::withoutGlobalScope(TenantScope::class)
                ->where('work_breakdown_task_id', $task->id)
                ->whereBetween('scheduled_date', [$periodStart, $periodEnd])
                ->pluck('scheduled_date')
                ->map(function (mixed $date): string {
                    /** @var string $date */
                    return Carbon::parse($date)->toDateString();
                })
                ->all();

            $intervalDays = $task->frequency->intervalDays();

            if ($intervalDays === null) {
                if (! $this->isWithinContractWindow($periodStart, $contract)) {
                    continue;
                }

                // One-time task: at most one job ever, regardless of which period window
                // generation happens to run in — not limited to $existingDates (period-scoped).
                if ($task->jobs()->withoutGlobalScope(TenantScope::class)->exists()) {
                    continue;
                }

                $this->createRecurringJob($breakdown, $task, $periodStart);
                $created++;

                continue;
            }

            foreach ($period as $rawDay) {
                /** @var DateTimeInterface $rawDay */
                $day = Carbon::instance($rawDay);

                if ($day->lt($referenceDate) || ! $this->isWithinContractWindow($day, $contract)) {
                    continue;
                }

                if (in_array($day->toDateString(), $existingDates, true)) {
                    continue;
                }

                if ($referenceDate->diffInDays($day) % $intervalDays !== 0) {
                    continue;
                }

                $this->createRecurringJob($breakdown, $task, $day);
                $created++;
            }
        }

        return $created;
    }

    private function isWithinContractWindow(Carbon $day, ?Contract $contract): bool
    {
        if ($contract === null) {
            return true;
        }

        if ($day->lt($contract->valid_from)) {
            return false;
        }

        return $contract->end_date === null || ! $day->gt($contract->end_date);
    }

    private function createRecurringJob(WorkBreakdown $breakdown, WorkBreakdownTask $task, Carbon $day): void
    {
        ScheduledJob::create([
            'tenant_id' => $breakdown->tenant_id,
            'cleaning_object_id' => $breakdown->cleaning_object_id,
            'work_breakdown_id' => $breakdown->id,
            'work_breakdown_task_id' => $task->id,
            'contract_id' => $breakdown->contract_id,
            'type' => JobTypeEnum::Regular,
            'status' => JobStatusEnum::Unassigned,
            'scheduled_date' => $day->toDateString(),
        ]);
    }
}
