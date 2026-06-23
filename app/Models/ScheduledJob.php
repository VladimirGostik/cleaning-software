<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use Database\Factories\ScheduledJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
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
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property CleaningObject|null $cleaningObject
 * @property TenantMembership|null $assignedMembership
 * @property WorkBreakdown|null $workBreakdown
 * @property WorkBreakdownTask|null $workBreakdownTask
 * @property Contract|null $contract
 * @property Invoice|null $invoice
 */
#[Table('cleaning_jobs')]
#[Fillable([
    'tenant_id',
    'cleaning_object_id',
    'assigned_membership_id',
    'work_breakdown_id',
    'work_breakdown_task_id',
    'contract_id',
    'invoice_id',
    'type',
    'status',
    'scheduled_date',
    'start_time',
    'end_time',
    'note',
    'gps_lat',
    'gps_lng',
    'completed_at',
    'cancelled_at',
])]
final class ScheduledJob extends Model
{
    /** @use HasFactory<ScheduledJobFactory> */
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
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
            ->logOnly(['status', 'assigned_membership_id', 'scheduled_date', 'type'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function cleaningObject(): BelongsTo
    {
        return $this->belongsTo(CleaningObject::class, 'cleaning_object_id');
    }

    public function assignedMembership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'assigned_membership_id');
    }

    public function workBreakdown(): BelongsTo
    {
        return $this->belongsTo(WorkBreakdown::class, 'work_breakdown_id');
    }

    public function workBreakdownTask(): BelongsTo
    {
        return $this->belongsTo(WorkBreakdownTask::class, 'work_breakdown_task_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [JobStatusEnum::Unassigned, JobStatusEnum::Planned], true);
    }

    public function canBeAssigned(): bool
    {
        return in_array($this->status, [JobStatusEnum::Unassigned, JobStatusEnum::Planned], true);
    }
}
