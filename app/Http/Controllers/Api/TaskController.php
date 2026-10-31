<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Tasks\TaskAssignData;
use App\Data\Tasks\TaskDetailData;
use App\Data\Tasks\TaskFormContextData;
use App\Data\Tasks\TaskListItemData;
use App\Data\Tasks\TaskUpsertData;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;

#[Group('Tasks', 'Firm and global tasks')]
#[Authenticated]
final class TaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    #[Endpoint('List tasks', 'Tasks across all companies (scope=all) or the active company plus own global tasks (scope=company, default).')]
    #[QueryParam('scope', 'string', 'all or company.', required: false, example: 'all')]
    #[QueryParam('filter[status]', 'string', 'open, done or cancelled.', required: false, example: 'open')]
    #[QueryParam('filter[priority]', 'string', 'low, normal or high.', required: false, example: 'high')]
    #[QueryParam('filter[kind]', 'string', 'firm or global.', required: false, example: 'firm')]
    #[QueryParam('filter[tenant_id]', 'string', 'Restrict to one company.', required: false)]
    #[QueryParam('filter[assigned_membership_id]', 'string', 'Restrict to one assignee.', required: false)]
    #[QueryParam('filter[search]', 'string', 'Title or description contains.', required: false)]
    #[QueryParam('sort', 'string', 'due_at, created_at, status, title; prefix - for descending.', required: false, example: 'due_at')]
    #[ResponseFromSpatieData(TaskListItemData::class, User::class, paginated: true)]
    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $acrossCompanies = $request->query('scope') === 'all';

        Gate::forUser($actor)->authorize($acrossCompanies ? 'viewAnyAcrossCompanies' : 'viewAny', Task::class);

        if ($acrossCompanies) {
            return response()->json($this->tasks->listAcrossCompanies($request, $actor));
        }

        return response()->json($this->tasks->paginate($request, $actor));
    }

    #[Authorize('create', Task::class)]
    #[Endpoint('Task form context', 'Companies where the user may create tasks, and their assignable members.')]
    #[ResponseFromSpatieData(TaskFormContextData::class, User::class)]
    public function formContext(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->tasks->formContext($actor));
    }

    #[Authorize('create', Task::class)]
    #[Endpoint('Create task', 'Omit tenant_id for the active company, send null for a global task.')]
    #[ResponseFromSpatieData(TaskDetailData::class, User::class)]
    public function store(TaskUpsertData $data, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->tasks->assertMayCreate($data, $actor);

        $task = $this->tasks->create($data, $actor);

        return response()->json($this->tasks->detail($task, $actor), 201);
    }

    #[Authorize('view', 'task')]
    #[Endpoint('Show task', 'The task must belong to the active company (X-Tenant-Id) or be an own global task.')]
    #[ResponseFromSpatieData(TaskDetailData::class, User::class)]
    public function show(Task $task, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->tasks->detail($task, $actor));
    }

    #[Authorize('update', 'task')]
    #[Endpoint('Update task', 'Tenant and assignee are not changed here; use the assign endpoint.')]
    #[ResponseFromSpatieData(TaskDetailData::class, User::class)]
    public function update(TaskUpsertData $data, Task $task, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->tasks->detail($this->tasks->update($task, $data, $actor), $actor));
    }

    #[Authorize('delete', 'task')]
    #[Endpoint('Delete task')]
    public function destroy(Task $task): Response
    {
        $this->tasks->delete($task);

        return response()->noContent();
    }

    #[Authorize('update', 'task')]
    #[Endpoint('Complete task')]
    #[ResponseFromSpatieData(TaskDetailData::class, User::class)]
    public function complete(Task $task, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->tasks->detail($this->tasks->complete($task), $actor));
    }

    #[Authorize('update', 'task')]
    #[Endpoint('Reopen task')]
    #[ResponseFromSpatieData(TaskDetailData::class, User::class)]
    public function reopen(Task $task, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->tasks->detail($this->tasks->reopen($task), $actor));
    }

    #[Authorize('assign', 'task')]
    #[Endpoint('Assign task', 'Firm tasks only; send null to unassign.')]
    #[ResponseFromSpatieData(TaskDetailData::class, User::class)]
    public function assign(TaskAssignData $data, Task $task, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->tasks->detail($this->tasks->assign($task, $data, $actor), $actor));
    }
}
