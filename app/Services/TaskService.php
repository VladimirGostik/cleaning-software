<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Auth\MobileCompanyData;
use App\Data\Tasks\TaskAssignData;
use App\Data\Tasks\TaskBoardColumnData;
use App\Data\Tasks\TaskBoardData;
use App\Data\Tasks\TaskCanData;
use App\Data\Tasks\TaskDetailData;
use App\Data\Tasks\TaskFormContextData;
use App\Data\Tasks\TaskListItemData;
use App\Data\Tasks\TaskMemberOptionData;
use App\Data\Tasks\TaskPatchData;
use App\Data\Tasks\TaskReminderSlotData;
use App\Data\Tasks\TaskUpsertData;
use App\Enums\PermissionEnum;
use App\Enums\TaskCommentAuthorEnum;
use App\Enums\TaskReminderSlotEnum;
use App\Enums\TaskRunStatusEnum;
use App\Enums\TaskStatusEnum;
use App\Events\TaskAssigned;
use App\Events\TaskDue;
use App\Models\Task;
use App\Models\TenantMembership;
use App\Models\User;
use App\Policies\TaskPolicy;
use App\Scopes\TaskCommentScope;
use App\Scopes\TenantScope;
use App\Services\Dashboard\TenantVisibility;
use App\Services\Tenancy\TenantPermissionSnapshot;
use App\Utils\AllowedFilter;
use App\Utils\Filters;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;
use Spatie\Permission\PermissionRegistrar;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\Sorts\Sort;

final readonly class TaskService
{
    public const string PRIORITY_RANK = "CASE tasks.priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END";

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
        return $this->page(Task::query()->visibleTo($actor), $request, $actor, $this->boundRights($actor));
    }

    /**
     * @return LengthAwarePaginator<int, TaskListItemData>
     */
    public function listAcrossCompanies(Request $request, User $actor): LengthAwarePaginator
    {
        $visibilities = $this->taskVisibilities($actor);

        return $this->page($this->acrossCompaniesBase($visibilities, $actor), $request, $actor, $this->rightsOf($visibilities));
    }

    public function board(Request $request, User $actor): TaskBoardData
    {
        return $this->buildBoard(Task::query()->visibleTo($actor), $request, $actor, $this->boundRights($actor));
    }

    public function boardAcrossCompanies(Request $request, User $actor): TaskBoardData
    {
        $visibilities = $this->taskVisibilities($actor);

        return $this->buildBoard($this->acrossCompaniesBase($visibilities, $actor), $request, $actor, $this->rightsOf($visibilities));
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
            ->whereNotNull('user_id')
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

        [$defaultOn, $defaultSlot] = TaskReminderSlotEnum::firstAfter(now());

        return new TaskFormContextData(
            companies: array_map(MobileCompanyData::fromVisibility(...), $creatable),
            members: $members,
            reminder_slots: TaskReminderSlotData::forAllSlots(),
            default_remind_on: $defaultOn,
            default_remind_slot: $defaultSlot,
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
                'status' => $data->status ?? TaskStatusEnum::Open,
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
                'remind_at' => $this->remindAtFor($task, $data),
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

    public function patch(Task $task, TaskPatchData $data, User $actor): Task
    {
        $alias = array_search($task->taskable_type, Task::TASKABLE_TYPES, true);
        $pairSent = ! $data->remind_on instanceof Optional && ! $data->remind_slot instanceof Optional;

        $remindAt = match (true) {
            $pairSent => null,
            ! $data->remind_at instanceof Optional => $data->remind_at,
            default => $task->remind_at?->toIso8601String(),
        };

        return $this->update($task, new TaskUpsertData(
            title: $data->title instanceof Optional ? $task->title : $data->title,
            priority: $data->priority instanceof Optional ? $task->priority : $data->priority,
            tenant_id: $task->tenant_id,
            description: $data->description instanceof Optional ? $task->description : $data->description,
            due_at: $data->due_at instanceof Optional ? $task->due_at?->toIso8601String() : $data->due_at,
            remind_at: $remindAt,
            remind_on: $pairSent ? $data->remind_on : null,
            remind_slot: $pairSent ? $data->remind_slot : null,
            taskable_type: is_string($alias) ? $alias : null,
            taskable_id: $task->taskable_id,
        ), $actor);
    }

    /** A reminder the request does not actually change (same minute, or the slot pair derived from it) is kept as stored. */
    private function remindAtFor(Task $task, TaskUpsertData $data): ?CarbonInterface
    {
        $stored = $task->remind_at;

        if ($stored !== null) {
            $sameMinute = $data->requestedRemindAtUtc()?->startOfMinute()->equalTo($stored->copy()->startOfMinute()) === true;
            $samePair = $data->remind_slot !== null
                && TaskReminderSlotEnum::dateAndSlotFor($stored) === [$data->remind_on, $data->remind_slot];

            if ($sameMinute || $samePair) {
                return $stored;
            }
        }

        return $data->remindAtUtc();
    }

    public function complete(Task $task): Task
    {
        if (! $task->isActive()) {
            throw ValidationException::withMessages(['status' => [__('app.task_not_open')]]);
        }

        return $this->changeStatus($task, TaskStatusEnum::Done);
    }

    public function reopen(Task $task): Task
    {
        if ($task->isActive()) {
            throw ValidationException::withMessages(['status' => [__('app.task_not_closed')]]);
        }

        return $this->changeStatus($task, TaskStatusEnum::Open);
    }

    /** The same status writes nothing; a reminder already sent is not re-armed. */
    public function changeStatus(Task $task, TaskStatusEnum $status): Task
    {
        if ($task->status === $status) {
            return $this->hydrate($task);
        }

        return $this->db->transaction(function () use ($task, $status): Task {
            $task->update([
                'status' => $status,
                'completed_at' => $status === TaskStatusEnum::Done ? now() : null,
            ]);

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

    public function findVisible(string $id, User $actor): ?Task
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $task = Task::query()->find($id);

        return $task !== null && $actor->can('view', $task) ? $task : null;
    }

    public function delete(Task $task): void
    {
        $this->db->transaction(fn () => $task->delete());
    }

    /** The only loader for queue and console contexts, where the scope would drop every global task. */
    public function findForSystem(string $id, bool $withTrashed = false): Task
    {
        return Task::unscopedForSystem()->when($withTrashed, fn (Builder $query) => $query->withTrashed())->findOrFail($id);
    }

    /** Membership deletion (`$onlyOpen = false`) must release every row for the restrict FK. */
    public function unassignForMembership(TenantMembership $membership, bool $onlyOpen = true): int
    {
        return Task::query()
            ->when(! $onlyOpen, fn (Builder $query) => $query->withTrashed())
            ->where('tenant_id', $membership->tenant_id)
            ->where('assigned_membership_id', $membership->id)
            ->when($onlyOpen, fn (Builder $query) => $query->whereIn('status', TaskStatusEnum::activeValues()))
            ->update(['assigned_membership_id' => null]);
    }

    public function sendReminder(Task $task): void
    {
        $this->db->transaction(function () use ($task): void {
            $claimed = Task::unscopedForSystem()
                ->whereKey($task->id)
                ->whereNull('reminded_at')
                ->whereIn('status', TaskStatusEnum::activeValues())
                ->update(['reminded_at' => now()]);

            if ($claimed === 1) {
                TaskDue::dispatch($task->id);
            }
        });
    }

    /**
     * @param  Builder<Task>  $base
     * @param  array<string, array{view_all: bool, edit: bool}>  $rights
     * @return LengthAwarePaginator<int, TaskListItemData>
     */
    private function page(Builder $base, Request $request, User $actor, array $rights): LengthAwarePaginator
    {
        return $this->filtered($base, $request, $actor)
            ->allowedSorts('due_at', 'created_at', 'status', 'title', $this->prioritySort())
            ->defaultSort('due_at', '-created_at')
            ->with([...$this->listRelations(), 'latestAiRunStatus'])
            ->withCount($this->listCounts())
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (Task $task) => $this->listItem($task, $actor, $rights));
    }

    /**
     * Columns are the statuses, so `filter[status]`, `sort` and paging are dropped from the request.
     *
     * @param  Builder<Task>  $base
     * @param  array<string, array{view_all: bool, edit: bool}>  $rights
     */
    private function buildBoard(Builder $base, Request $request, User $actor, array $rights): TaskBoardData
    {
        $query = Arr::except($request->query(), ['sort', 'page', 'per_page']);

        if (isset($query['filter']) && is_array($query['filter'])) {
            unset($query['filter']['status']);
        }

        $filtered = $this->filtered($base, $request->duplicate($query), $actor)->getEloquentBuilder();
        $limit = max(1, config()->integer('tasks.board.column_limit', 50));

        $totals = (clone $filtered)->toBase()
            ->select('tasks.status')
            ->selectRaw('count(*) as total')
            ->groupBy('tasks.status')
            ->pluck('total', 'status');

        $columns = [];

        foreach (TaskStatusEnum::boardOrder() as $status) {
            $column = (clone $filtered)
                ->where('tasks.status', $status->value)
                ->with([...$this->listRelations(), 'latestAiRunStatus'])
                ->withCount($this->listCounts());

            $this->orderColumn($column, $status);

            $tasks = $column->limit($limit + 1)->get();

            $columns[] = new TaskBoardColumnData(
                status: $status,
                total: $this->intOf($totals->get($status->value)),
                tasks: array_values($tasks->take($limit)->map(fn (Task $task): TaskListItemData => $this->listItem($task, $actor, $rights))->all()),
                has_more: $tasks->count() > $limit,
            );
        }

        return new TaskBoardData(columns: $columns);
    }

    private function intOf(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /** @param  Builder<Task>  $column */
    private function orderColumn(Builder $column, TaskStatusEnum $status): void
    {
        if ($status === TaskStatusEnum::Done) {
            $column->orderByRaw('tasks.completed_at DESC NULLS LAST')->orderByDesc('tasks.updated_at');

            return;
        }

        $column->orderByRaw(self::PRIORITY_RANK.' ASC')
            ->orderByRaw('tasks.due_at ASC NULLS LAST')
            ->orderByDesc('tasks.created_at');
    }

    /**
     * @param  Builder<Task>  $base
     * @return QueryBuilder<Task>
     */
    private function filtered(Builder $base, Request $request, User $actor): QueryBuilder
    {
        return QueryBuilder::for($base, $request)
            ->allowedFilters(
                AllowedFilter::callbackClean('search', function (Builder $query, mixed $value): void {
                    if (blank($value) || ! is_scalar($value)) {
                        return;
                    }

                    $query->where(function (Builder $q) use ($value): void {
                        Filters::whereContains($q, 'tasks.title', (string) $value);
                        Filters::whereContains($q, 'tasks.description', (string) $value, 'or');
                    });
                }),
                AllowedFilter::callbackClean('kind', function (Builder $query, mixed $value): void {
                    match ($value) {
                        'global' => $query->whereNull('tasks.tenant_id'),
                        'firm' => $query->whereNotNull('tasks.tenant_id'),
                        default => null,
                    };
                }),
                AllowedFilter::callback('status', function (Builder $query, mixed $value): void {
                    $values = array_values(array_filter(Arr::wrap($value), is_string(...)));

                    if ($values !== []) {
                        $query->whereIn('tasks.status', $values);
                    }
                }),
                AllowedFilter::dynamic('priority'),
                AllowedFilter::dynamic('tenant_id')->uuid(),
                AllowedFilter::dynamic('assigned_membership_id')->uuid(),
                AllowedFilter::dynamic('due_at')->date(),
                AllowedFilter::callbackClean('assignee', function (Builder $query, mixed $value) use ($actor): void {
                    if ($value === 'me') {
                        $query->whereIn('tasks.assigned_membership_id', TenantMembership::query()
                            ->where('user_id', $actor->id)
                            ->where('is_active', true)
                            ->select('id'));
                    } elseif ($value === 'none') {
                        $query->whereNull('tasks.assigned_membership_id');
                    } elseif (is_string($value) && Str::isUuid($value)) {
                        $query->where('tasks.assigned_membership_id', $value);
                    }
                }),
                AllowedFilter::callbackClean('due', function (Builder $query, mixed $value): void {
                    $this->applyDue($query, $value);
                }),
            );
    }

    /** @param  Builder<Task>  $query */
    private function applyDue(Builder $query, mixed $value): void
    {
        $today = CarbonImmutable::now(TaskReminderSlotEnum::TIMEZONE)->startOfDay();

        match ($value) {
            'overdue' => $query->where('tasks.due_at', '<', now())->whereIn('tasks.status', TaskStatusEnum::activeValues()),
            'today' => $query->where('tasks.due_at', '>=', $today->utc())->where('tasks.due_at', '<', $today->addDay()->startOfDay()->utc()),
            'week' => $query->where('tasks.due_at', '>=', $today->utc())->where('tasks.due_at', '<', $today->addDays(7)->startOfDay()->utc()),
            'none' => $query->whereNull('tasks.due_at'),
            default => null,
        };
    }

    private function prioritySort(): AllowedSort
    {
        return AllowedSort::custom('priority', new class implements Sort
        {
            /** @param  Builder<Model>  $query */
            public function __invoke(Builder $query, bool $descending, string $property): void
            {
                $query->orderByRaw(TaskService::PRIORITY_RANK.($descending ? ' DESC' : ' ASC'));
                $query->orderBy('tasks.created_at', 'desc')->orderBy('tasks.id');
            }
        });
    }

    /** @return array<string, Closure> */
    private function listCounts(): array
    {
        return [
            'comments as comments_count' => fn (Builder $query) => $query
                ->withoutGlobalScope(TaskCommentScope::class)
                ->where('author_kind', TaskCommentAuthorEnum::User->value),
            'media as attachments_count' => fn (Builder $query) => $query->where('collection_name', Task::ATTACHMENTS_COLLECTION),
        ];
    }

    /** @param  array<string, array{view_all: bool, edit: bool}>  $rights */
    private function listItem(Task $task, User $actor, array $rights): TaskListItemData
    {
        $tenantRights = $task->tenant_id === null ? null : ($rights[$task->tenant_id] ?? null);

        $canUpdate = TaskPolicy::changeAllowed($task->isGlobal(), $task->created_by_user_id === $actor->id, $tenantRights['edit'] ?? false);

        return TaskListItemData::fromModel($task, $canUpdate, $this->aiRunStatus($task, $actor, $tenantRights['view_all'] ?? false));
    }

    private function aiRunStatus(Task $task, User $actor, bool $holdsViewAll): ?TaskRunStatusEnum
    {
        $run = $task->latestAiRunStatus;

        if ($run === null) {
            return null;
        }

        $audience = TaskPolicy::aiOutputAudienceIncludes(
            $task->isGlobal(),
            $task->created_by_user_id === $actor->id,
            $task->tenant_id !== null && $holdsViewAll,
            $run->started_by_user_id === $actor->id,
        );

        return $audience ? $run->status : null;
    }

    /** @return array<string, array{view_all: bool, edit: bool}> */
    private function boundRights(User $actor): array
    {
        return [current_tenant_id() => [
            'view_all' => $actor->can(PermissionEnum::ViewAllTasks->value),
            'edit' => $actor->can(PermissionEnum::EditTasks->value),
        ]];
    }

    /**
     * @param  list<TenantVisibility>  $visibilities
     * @return array<string, array{view_all: bool, edit: bool}>
     */
    private function rightsOf(array $visibilities): array
    {
        $rights = [];

        foreach ($visibilities as $visibility) {
            $rights[$visibility->tenant->id] = [
                'view_all' => $visibility->tasksAll,
                'edit' => in_array(PermissionEnum::EditTasks, $visibility->permissions, true),
            ];
        }

        return $rights;
    }

    /** @return list<TenantVisibility> */
    private function taskVisibilities(User $actor): array
    {
        return array_values(array_filter(
            $this->snapshot->forRequest($actor),
            fn (TenantVisibility $visibility): bool => $visibility->tasks,
        ));
    }

    /**
     * The scope only knows the bound tenant, so the visibility predicate is re-applied per snapshot tenant.
     *
     * @param  list<TenantVisibility>  $visibilities
     * @return Builder<Task>
     */
    private function acrossCompaniesBase(array $visibilities, User $actor): Builder
    {
        return Task::unscopedForSystem()->where(function (Builder $query) use ($visibilities, $actor): void {
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
