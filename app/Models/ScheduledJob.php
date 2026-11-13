<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Enums\PermissionEnum;
use App\Rules\AllowedUploadFile;
use Carbon\CarbonInterface;
use Database\Factories\ScheduledJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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
 * @property string|null $client_uuid
 * @property string|null $gps_lat
 * @property string|null $gps_lng
 * @property Carbon|null $started_at
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
 * @property Collection<int, ScheduledJobItem> $items
 * @property int|null $photos_count
 */
#[Table('cleaning_jobs')]
#[Fillable([
    'tenant_id', 'cleaning_object_id', 'assigned_membership_id', 'work_breakdown_id',
    'work_breakdown_task_id', 'contract_id', 'invoice_id', 'type', 'status',
    'scheduled_date', 'start_time', 'end_time', 'note', 'gps_lat', 'gps_lng',
    'started_at', 'completed_at', 'cancelled_at', 'client_uuid',
])]
final class ScheduledJob extends Model implements HasMedia
{
    /** @use HasFactory<ScheduledJobFactory> */
    use BelongsToTenant, HasFactory, HasUuids, InteractsWithMedia, LogsActivity, SoftDeletes;

    public const string PHOTOS_COLLECTION = 'photos';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => JobTypeEnum::class,
            'status' => JobStatusEnum::class,
            'scheduled_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $disk = config('scheduling.work_records.photos.disk', 'local');

        $this->addMediaCollection(self::PHOTOS_COLLECTION)
            ->acceptsMimeTypes(AllowedUploadFile::detectedMimes('scheduling.work_records.photos'))
            ->useDisk(is_string($disk) ? $disk : 'local');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assigned_membership_id', 'scheduled_date', 'start_time', 'end_time', 'type', 'completed_at'])
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

    /** @return HasMany<ScheduledJobItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ScheduledJobItem::class, 'cleaning_job_id')->orderBy('name')->orderBy('id');
    }

    public function isWorkRecord(): bool
    {
        if ($this->type !== JobTypeEnum::OneOff) {
            return false;
        }

        return $this->relationLoaded('items') ? $this->items->isNotEmpty() : $this->items()->exists();
    }

    public function isInvoiced(): bool
    {
        return $this->invoice_id !== null;
    }

    /**
     * @param  Builder<ScheduledJob>  $query
     * @return Builder<ScheduledJob>
     */
    public function scopeWorkRecords(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('type'), JobTypeEnum::OneOff)
            ->whereHas('items', fn (Builder $items) => $items->withoutGlobalScopes());
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

    public function canBeStarted(): bool
    {
        return $this->status->canTransitionTo(JobStatusEnum::InProgress);
    }

    public function canBeCompleted(): bool
    {
        return $this->status->canTransitionTo(JobStatusEnum::Completed);
    }

    public function canBeUnapproved(): bool
    {
        return $this->status->canTransitionTo(JobStatusEnum::Unapproved);
    }

    public function isAssignedTo(User $actor): bool
    {
        return $this->assigned_membership_id !== null
            && $this->assigned_membership_id === $actor->activeMembershipId();
    }

    public function isCompletableByAssignee(CarbonInterface $today): bool
    {
        return $this->canBeCompleted() && $this->scheduled_date->toDateString() <= $today->toDateString();
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
