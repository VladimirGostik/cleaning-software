<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Auth\MobileCompanyData;
use App\Data\Tasks\TaskAssignData;
use App\Data\Tasks\TaskCanData;
use App\Data\Tasks\TaskDetailData;
use App\Data\Tasks\TaskFormContextData;
use App\Data\Tasks\TaskListItemData;
use App\Data\Tasks\TaskMemberOptionData;
use App\Data\Tasks\TaskUpsertData;
use App\Enums\PermissionEnum;
use App\Enums\TaskStatusEnum;
use App\Events\TaskAssigned;
use App\Events\TaskDue;
use App\Models\Task;
use App\Models\TenantMembership;
use App\Models\User;
use App\Scopes\TenantScope;
use App\Services\Dashboard\TenantVisibility;
use App\Services\Tenancy\TenantPermissionSnapshot;
use App\Utils\AllowedFilter;
use App\Utils\Filters;
use Closure;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class TaskService
{
    public function __construct(
        private DatabaseManager $db,
        private TenantPermissionSnapshot $snapshot,
        private PermissionRegistrar $registrar,
    ) {}

    /**
     * @return LengthAwarePaginator<int, TaskListItemData>
     */
    public function paginate(Request $request, User $actor): LengthAwarePaginator
    {
        return $this->page(Task::query()->visibleTo($actor), $request);
    }

    /**
     * The scope only knows the bound tenant, so the visibility predicate is re-applied per snapshot tenant.
     *
     * @return LengthAwarePaginator<int, TaskListItemData>
     */
    public function listAcrossCompanies(Request $request, User $actor): LengthAwarePaginator
    {
        $visibilities = array_filter(
            $this->snapshot->forRequest($actor),
            fn (TenantVisibility $visibility): bool => $visibility->tasks,
        );

        $base = Task::unscopedForSystem()->where(function (Builder $query) use ($visibilities, $actor): void {
            foreach ($visibilities as $visibility) {
                $query->orWhere(function (Builder $firm) use ($visibility, $actor): void {
                    $firm->where('tasks.tenant_id', $visibility->tenant->id);

                    if ($visibility->tasksAll) {
                        return;
                    }

                    $firm->where(function (Builder $own) use ($visibility, $actor): void {
                        $own->where('tasks.created_by_user_id', $actor->id);

                        if ($visibility->membershipId !== null) {
                            $own->orWhere('tasks.assigned_membership_id', $visibility->membershipId);
                        }
                    });
                });
            }

            $query->orWhere(function (Builder $global) use ($actor): void {
                $global->whereNull('tasks.tenant_id')->where('tasks.created_by_user_id', $actor->id);
            });
        });

        return $this->page($base, $request);
    }

    public function formContext(User $actor): TaskFormContextData
    {
        $visibilities = $this->snapshot->forRequest($actor);

        $creatable = array_values(array_filter(
            $visibilities,
            fn (TenantVisibility $visibility): bool => in_array(PermissionEnum::CreateTasks, $visibility->permissions, true),
        ));

        $assignableIds = TenantVisibility::idsWhere(
            $creatable,
            fn (TenantVisibility $visibility): bool => in_array(PermissionEnum::EditTasks, $visibility->permissions, true),
        );

        /** @var Collection<string, Collection<int, TenantMembership>> $byTenant */
        $byTenant = TenantMembership::query()
            ->whereIn('tenant_id', $assignableIds)
            ->where('is_active', true)
            ->with('user:id,name,email')
            ->get()
            ->groupBy('tenant_id');

        $members = [];

        foreach ($creatable as $visibility) {
            $members[$visibility->tenant->id] = array_values(
                $byTenant->get($visibility->tenant->id, collect())
                    ->map(fn (TenantMembership $membership): TaskMemberOptionData => TaskMemberOptionData::fromMembership($membership))
                    ->sortBy('name')
                    ->all(),
            );
        }

        return new TaskFormContextData(
            companies: array_map(MobileCompanyData::fromVisibility(...), $creatable),
            members: $members,
        );
    }

    public function detail(Task $task, User $actor): TaskDetailData
    {
        $task->loadMissing([...$this->listRelations(), 'creator:id,name']);

        return TaskDetailData::fromModel($task, new TaskCanData(
            update: $actor->can('update', $task),
            delete: $actor->can('delete', $task),
            assign: ! $task->isGlobal() && $actor->can('assign', $task),
        ), $task->media()->where('collection_name', Task::ATTACHMENTS_COLLECTION)->count());
    }

    /** Create permission (and, when assigning, edit permission) in the target tenant, or anywhere for a global task. */
    public function assertMayCreate(TaskUpsertData $data, User $actor): void
    {
        $tenantId = $data->resolvedTenantId();
        $gate = Gate::forUser($actor);

        $gate->authorize('create', [Task::class, $tenantId]);

        if ($data->assigned_membership_id !== null && $tenantId !== null) {
            $gate->authorize('assignIn', [Task::class, $tenantId]);
        }
    }

    public function create(TaskUpsertData $data, User $actor): Task
    {
        $tenantId = $data->resolvedTenantId();

        $subjectClass = $this->subjectClass($tenantId, $data, $actor);

        return $this->db->transaction(function () use ($data, $actor, $tenantId, $subjectClass): Task {
            $task = Task::create([
                'tenant_id' => $tenantId,
                'created_by_user_id' => $actor->id,
                'assigned_membership_id' => $data->assigned_membership_id,
                'title' => $data->title,
                'description' => $data->description,
                'status' => TaskStatusEnum::Open,
                'priority' => $data->priority,
                'due_at' => $data->dueAtUtc(),
                'remind_at' => $data->remindAtUtc(),
                'source' => $data->source,
                'taskable_type' => $subjectClass,
                'taskable_id' => $data->taskable_id,
            ]);

            if ($task->assigned_membership_id !== null) {
                $this->notifyAssignee($task, $actor);
            }

            return $this->hydrate($task);
        });
    }

    /** Tenant and assignee are not changed here; see `assign`. */
    public function update(Task $task, TaskUpsertData $data, User $actor): Task
    {
        $unchanged = $data->taskable_type !== null
            && (Task::TASKABLE_TYPES[$data->taskable_type] ?? null) === $task->taskable_type
            && $data->taskable_id === $task->taskable_id;
        $subjectClass = $unchanged ? $task->taskable_type : $this->subjectClass($task->tenant_id, $data, $actor);

        return $this->db->transaction(function () use ($task, $data, $subjectClass): Task {
            $remindBefore = $task->remind_at?->copy()->startOfMinute()->timestamp;

            $task->fill([
                'title' => $data->title,
                'description' => $data->description,
                'priority' => $data->priority,
                'due_at' => $data->dueAtUtc(),
                'remind_at' => $data->remindAtUtc(),
                'taskable_type' => $subjectClass,
                'taskable_id' => $data->taskable_id,
            ]);

            if ($remindBefore !== $task->remind_at?->copy()->startOfMinute()->timestamp) {
                $task->reminded_at = null;
            }

            $task->save();

            return $this->hydrate($task);
        });
    }

    public function complete(Task $task): Task
    {
        if (! $task->isOpen()) {
            throw ValidationException::withMessages(['status' => [__('app.task_not_open')]]);
        }

        return $this->db->transaction(function () use ($task): Task {
            $task->update(['status' => TaskStatusEnum::Done, 'completed_at' => now()]);

            return $this->hydrate($task);
        });
    }

    public function reopen(Task $task): Task
    {
        if ($task->isOpen()) {
            throw ValidationException::withMessages(['status' => [__('app.task_not_closed')]]);
        }

        return $this->db->transaction(function () use ($task): Task {
            $task->update(['status' => TaskStatusEnum::Open, 'completed_at' => null]);

            return $this->hydrate($task);
        });
    }

    public function assign(Task $task, TaskAssignData $data, User $actor): Task
    {
        if ($task->isGlobal() && $data->assigned_membership_id !== null) {
            throw ValidationException::withMessages([
                'assigned_membership_id' => [__('app.task_global_not_assignable')],
            ]);
        }

        return $this->db->transaction(function () use ($task, $data, $actor): Task {
            $task->assigned_membership_id = $data->assigned_membership_id;
            $changed = $task->isDirty('assigned_membership_id');
            $task->save();

            if ($changed && $task->assigned_membership_id !== null) {
                $this->notifyAssignee($task, $actor);
            }

            return $this->hydrate($task);
        });
    }

    public function delete(Task $task): void
    {
        $this->db->transaction(fn () => $task->delete());
    }

    /** The only loader for queue and console contexts, where the scope would drop every global task. */
    public function findForSystem(string $id): Task
    {
        return Task::unscopedForSystem()->findOrFail($id);
    }

    /** Membership deletion (`$onlyOpen = false`) must release every row for the restrict FK. */
    public function unassignForMembership(TenantMembership $membership, bool $onlyOpen = true): int
    {
        return Task::query()
            ->when(! $onlyOpen, fn (Builder $query) => $query->withTrashed())
            ->where('tenant_id', $membership->tenant_id)
            ->where('assigned_membership_id', $membership->id)
            ->when($onlyOpen, fn (Builder $query) => $query->where('status', TaskStatusEnum::Open->value))
            ->update(['assigned_membership_id' => null]);
    }

    public function sendReminder(Task $task): void
    {
        $this->db->transaction(function () use ($task): void {
            $claimed = Task::unscopedForSystem()
                ->whereKey($task->id)
                ->whereNull('reminded_at')
                ->where('status', TaskStatusEnum::Open->value)
                ->update(['reminded_at' => now()]);

            if ($claimed === 1) {
                TaskDue::dispatch($task->id);
            }
        });
    }

    /**
     * @param  Builder<Task>  $base
     * @return LengthAwarePaginator<int, TaskListItemData>
     */
    private function page(Builder $base, Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::callbackClean('search', function (Builder $query, mixed $value): void {
                    if (blank($value) || ! is_scalar($value)) {
                        return;
                    }

                    $like = '%'.Filters::escapeLikeValue((string) $value).'%';
                    $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

                    $query->where(function (Builder $q) use ($like, $operator): void {
                        $q->where('tasks.title', $operator, $like)->orWhere('tasks.description', $operator, $like);
                    });
                }),
                AllowedFilter::callbackClean('kind', function (Builder $query, mixed $value): void {
                    match ($value) {
                        'global' => $query->whereNull('tasks.tenant_id'),
                        'firm' => $query->whereNotNull('tasks.tenant_id'),
                        default => null,
                    };
                }),
                AllowedFilter::dynamic('status'),
                AllowedFilter::dynamic('priority'),
                AllowedFilter::dynamic('tenant_id')->uuid(),
                AllowedFilter::dynamic('assigned_membership_id')->uuid(),
                AllowedFilter::dynamic('due_at')->date(),
            )
            ->allowedSorts('due_at', 'created_at', 'status', 'title')
            ->defaultSort('due_at', '-created_at')
            ->with($this->listRelations())
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (Task $task) => TaskListItemData::fromModel($task));
    }

    private function hydrate(Task $task): Task
    {
        return $task->load([...$this->listRelations(), 'creator:id,name']);
    }

    /** @return array<int|string, Closure|string> */
    private function listRelations(): array
    {
        return [
            'tenant:id,name',
            'assignedMembership.user:id,name,email',
            'taskable' => fn (MorphTo $morph) => $morph->constrain(Task::subjectConstraints()),
        ];
    }

    private function notifyAssignee(Task $task, User $actor): void
    {
        $assigneeUserId = TenantMembership::query()->whereKey($task->assigned_membership_id)->value('user_id');

        if ($assigneeUserId !== $actor->id) {
            TaskAssigned::dispatch($task->id);
        }
    }

    /**
     * Resolves the alias to its class and checks the subject lives in the task's own tenant.
     *
     * @return class-string<Model>|null
     */
    private function subjectClass(?string $tenantId, TaskUpsertData $data, User $actor): ?string
    {
        if ($data->taskable_type === null && $data->taskable_id === null) {
            return null;
        }

        if ($data->taskable_type === null || $data->taskable_id === null) {
            throw ValidationException::withMessages([
                $data->taskable_type === null ? 'taskable_type' : 'taskable_id' => [__('app.task_subject_invalid')],
            ]);
        }

        if ($tenantId === null) {
            throw ValidationException::withMessages(['taskable_type' => [__('app.task_global_no_subject')]]);
        }

        $class = Task::TASKABLE_TYPES[$data->taskable_type];

        $this->underTenant($actor, $tenantId, function () use ($class, $tenantId, $data, $actor): void {
            $subject = $class::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->whereKey($data->taskable_id)
                ->first();

            if ($subject === null) {
                throw ValidationException::withMessages(['taskable_id' => [__('app.task_subject_invalid')]]);
            }

            Gate::forUser($actor)->authorize('view', $subject);
        });

        return $class;
    }

    private function underTenant(User $actor, string $tenantId, Closure $callback): void
    {
        $hadBinding = app()->bound('current_tenant_id');
        /** @var string $previousTenant */
        $previousTenant = $hadBinding ? app('current_tenant_id') : '';
        $previousTeam = $this->registrar->getPermissionsTeamId();

        app()->instance('current_tenant_id', $tenantId);
        $this->registrar->setPermissionsTeamId($tenantId);
        $actor->unsetRelation('roles')->unsetRelation('permissions');

        try {
            $callback();
        } finally {
            if ($hadBinding) {
                app()->instance('current_tenant_id', $previousTenant);
            } else {
                app()->forgetInstance('current_tenant_id');
            }

            $this->registrar->setPermissionsTeamId($previousTeam);
            $actor->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
