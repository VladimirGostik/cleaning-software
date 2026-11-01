<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use App\Enums\PermissionEnum;
use App\Enums\TaskPriorityEnum;
use App\Enums\TaskSourceEnum;
use App\Enums\TaskStatusEnum;
use App\Rules\TaskAttachmentFile;
use App\Scopes\TaskScope;
use App\Scopes\TenantScope;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $created_by_user_id
 * @property string|null $assigned_membership_id
 * @property string $title
 * @property string|null $description
 * @property TaskStatusEnum $status
 * @property TaskPriorityEnum $priority
 * @property Carbon|null $due_at
 * @property Carbon|null $remind_at
 * @property Carbon|null $reminded_at
 * @property Carbon|null $completed_at
 * @property TaskSourceEnum $source
 * @property string|null $taskable_type
 * @property string|null $taskable_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property Tenant|null $tenant
 * @property User|null $creator
 * @property TenantMembership|null $assignedMembership
 * @property Model|null $taskable
 */
#[Fillable([
    'tenant_id', 'created_by_user_id', 'assigned_membership_id', 'title', 'description', 'status',
    'priority', 'due_at', 'remind_at', 'reminded_at', 'completed_at', 'source', 'taskable_type', 'taskable_id',
])]
#[ScopedBy(TaskScope::class)]
final class Task extends Model implements HasMedia
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasUuids, InteractsWithMedia, LogsActivity, SoftDeletes;

    public const string ATTACHMENTS_COLLECTION = 'attachments';

    /**
     * Alias => class stored in `taskable_type` (global morph aliases would change media/audit rows); only Task -> subject is supported.
     *
     * @var array<string, class-string<Model>>
     */
    public const array TASKABLE_TYPES = [
        'invoice' => Invoice::class,
        'quote' => Quote::class,
        'client' => Client::class,
        'cleaning_object' => CleaningObject::class,
        'scheduled_job' => ScheduledJob::class,
        'contract' => Contract::class,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TaskStatusEnum::class,
            'priority' => TaskPriorityEnum::class,
            'source' => TaskSourceEnum::class,
            'due_at' => 'datetime',
            'remind_at' => 'datetime',
            'reminded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $disk = config('tasks.attachments.disk', 'local');

        $this->addMediaCollection(self::ATTACHMENTS_COLLECTION)
            ->acceptsMimeTypes(TaskAttachmentFile::detectedMimes())
            ->useDisk(is_string($disk) ? $disk : 'local');
    }

    public function getActivitylogOptions(): LogOptions
    {
        $fields = $this->tenant_id === null
            ? ['status', 'priority', 'due_at']
            : ['title', 'description', 'status', 'priority', 'due_at', 'remind_at', 'assigned_membership_id', 'taskable_type', 'taskable_id'];

        return LogOptions::defaults()
            ->logOnly($fields)
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function beforeActivityLogged(Activity $activity, string $eventName): void
    {
        $activity->tenant_id = $this->tenant_id;
        $activity->keepNullTenant = $this->tenant_id === null;
    }

    /** @return Builder<Task> */
    public static function unscopedForSystem(): Builder
    {
        return self::query()->withoutGlobalScope(TaskScope::class);
    }

    /**
     * The subject lives in the task's own tenant, which need not be the bound one.
     *
     * @return array<class-string<Model>, callable(Builder<Model>): mixed>
     */
    public static function subjectConstraints(): array
    {
        $unscoped = static fn (Builder $query) => $query->withoutGlobalScope(TenantScope::class);

        return [
            Invoice::class => $unscoped,
            Quote::class => $unscoped,
            Client::class => $unscoped,
            CleaningObject::class => $unscoped,
            ScheduledJob::class => $unscoped,
            Contract::class => $unscoped,
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<TenantMembership, $this> */
    public function assignedMembership(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'assigned_membership_id');
    }

    /** @return HasMany<TaskComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    /**
     * A personal task's run belongs to the company chosen when starting it, which need not be the bound one.
     *
     * @return HasMany<TaskRun, $this>
     */
    public function aiRuns(): HasMany
    {
        return $this->hasMany(TaskRun::class)->withoutGlobalScope(TenantScope::class);
    }

    /** @return HasOne<TaskRun, $this> */
    public function latestAiRun(): HasOne
    {
        return $this->latestAiRunQuery();
    }

    /**
     * What a list page needs: no `actions` payload.
     *
     * @return HasOne<TaskRun, $this>
     */
    public function latestAiRunStatus(): HasOne
    {
        return $this->latestAiRunQuery()->select(['task_runs.id', 'task_runs.task_id', 'task_runs.status', 'task_runs.started_by_user_id']);
    }

    /**
     * Not `latestOfMany`: Postgres has no max(uuid), and the UUIDv7 id is the tie-break for runs created in the same second.
     *
     * @return HasOne<TaskRun, $this>
     */
    private function latestAiRunQuery(): HasOne
    {
        return $this->hasOne(TaskRun::class)
            ->withoutGlobalScope(TenantScope::class)
            ->whereRaw('task_runs.id = (select latest.id from task_runs latest where latest.task_id = task_runs.task_id order by latest.created_at desc, latest.id desc limit 1)');
    }

    /** @return MorphTo<Model, $this> */
    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isGlobal(): bool
    {
        return $this->tenant_id === null;
    }

    public function isOpen(): bool
    {
        return $this->status === TaskStatusEnum::Open;
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        $membershipId = $actor->activeMembershipId();
        $seesFirmTasks = $actor->can(PermissionEnum::ViewTasks->value);
        $seesAll = $seesFirmTasks && $actor->can(PermissionEnum::ViewAllTasks->value);

        return $query->where(function (Builder $q) use ($actor, $membershipId, $seesFirmTasks, $seesAll): void {
            $q->where(function (Builder $global) use ($actor): void {
                $global->whereNull($this->qualifyColumn('tenant_id'))
                    ->where($this->qualifyColumn('created_by_user_id'), $actor->id);
            });

            if (! $seesFirmTasks) {
                return;
            }

            $q->orWhere(function (Builder $firm) use ($actor, $membershipId, $seesAll): void {
                $firm->whereNotNull($this->qualifyColumn('tenant_id'));

                if ($seesAll) {
                    return;
                }

                $firm->where(function (Builder $own) use ($actor, $membershipId): void {
                    $own->where($this->qualifyColumn('created_by_user_id'), $actor->id);

                    if ($membershipId !== null) {
                        $own->orWhere($this->qualifyColumn('assigned_membership_id'), $membershipId);
                    }
                });
            });
        });
    }

    public function isVisibleTo(User $actor): bool
    {
        if ($this->isGlobal()) {
            return $this->created_by_user_id === $actor->id;
        }

        if ($this->tenant_id !== $this->boundTenantId() || ! $actor->can(PermissionEnum::ViewTasks->value)) {
            return false;
        }

        return $actor->can(PermissionEnum::ViewAllTasks->value)
            || $this->created_by_user_id === $actor->id
            || ($this->assigned_membership_id !== null && $this->assigned_membership_id === $actor->activeMembershipId());
    }

    private function boundTenantId(): ?string
    {
        /** @var string|null */
        return app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
    }
}
