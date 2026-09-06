<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Enums\PermissionEnum;
use Database\Factories\ScheduledJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $cleaning_object_id
 * @property string|null $assigned_membership_id
 * @property string|null $work_breakdown_id
 * @property string|null $work_breakdown_task_id
 * @property string|null $contract_id
 * @property string|null $invoice_id
 * @property JobTypeEnum $type
 * @property JobStatusEnum $status
 * @property Carbon $scheduled_date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string|null $note
 * @property string|null $gps_lat
 * @property string|null $gps_lng
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property CleaningObject|null $cleaningObject
 * @property TenantMembership|null $assignedMembership
 * @property WorkBreakdown|null $workBreakdown
 * @property WorkBreakdownTask|null $workBreakdownTask
 * @property Contract|null $contract
 * @property Invoice|null $invoice
 */
#[Table('cleaning_jobs')]
#[Fillable([
    'tenant_id', 'cleaning_object_id', 'assigned_membership_id', 'work_breakdown_id',
    'work_breakdown_task_id', 'contract_id', 'invoice_id', 'type', 'status',
    'scheduled_date', 'start_time', 'end_time', 'note', 'gps_lat', 'gps_lng',
    'completed_at', 'cancelled_at',
])]
final class ScheduledJob extends Model
{
    /** @use HasFactory<ScheduledJobFactory> */
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => JobTypeEnum::class,
            'status' => JobStatusEnum::class,
            'scheduled_date' => 'date',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assigned_membership_id', 'scheduled_date', 'start_time', 'end_time', 'type'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return BelongsTo<CleaningObject, $this> */
    public function cleaningObject(): BelongsTo
    {
        return $this->belongsTo(CleaningObject::class)->withTrashed();
    }

    /** @return BelongsTo<TenantMembership, $this> */
    public function assignedMembership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'assigned_membership_id');
    }

    /** @return BelongsTo<WorkBreakdown, $this> */
    public function workBreakdown(): BelongsTo
    {
        return $this->belongsTo(WorkBreakdown::class);
    }

    /** @return BelongsTo<WorkBreakdownTask, $this> */
    public function workBreakdownTask(): BelongsTo
    {
        return $this->belongsTo(WorkBreakdownTask::class);
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class)->withTrashed();
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function canBeAssigned(): bool
    {
        return in_array($this->status, [JobStatusEnum::Unassigned, JobStatusEnum::Planned], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [JobStatusEnum::Unassigned, JobStatusEnum::Planned], true);
    }

    /**
     * Fail-closed: with `ViewAllSchedule` the actor sees the tenant's jobs; without it, only
     * jobs assigned to her own active membership (or nothing, if she has none).
     *
     * @param  Builder<ScheduledJob>  $query
     * @return Builder<ScheduledJob>
     */
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->can(PermissionEnum::ViewAllSchedule->value)) {
            return $query;
        }

        $membershipId = $actor->activeMembershipId();

        if ($membershipId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->qualifyColumn('assigned_membership_id'), $membershipId);
    }

    public function isVisibleTo(User $actor): bool
    {
        if ($actor->can(PermissionEnum::ViewAllSchedule->value)) {
            return true;
        }

        return $this->assigned_membership_id !== null
            && $this->assigned_membership_id === $actor->activeMembershipId();
    }
}
