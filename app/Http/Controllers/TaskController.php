<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Tasks\TaskAssignData;
use App\Data\Tasks\TaskUpsertData;
use App\Enums\PermissionEnum;
use App\Models\Task;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\TaskAttachmentService;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

final class TaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks, private readonly TaskAttachmentService $attachments) {}

    #[Authorize('viewAny', Task::class)]
    #[NavItem(label: 'app.tasks', route: 'tasks.index', icon: 'CheckCircleIcon', permission: PermissionEnum::ViewTasks->value, order: 12)]
    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('Tasks/Index', [
            'tasks' => $this->tasks->paginate($request, $actor),
            'filters' => $request->query(),
            'context' => $this->tasks->formContext($actor),
        ]);
    }

    #[Authorize('create', Task::class)]
    public function store(TaskUpsertData $data, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->tasks->assertMayCreate($data, $actor);

        $task = $this->tasks->create($data, $actor);

        $visibleHere = $task->tenant_id === null || $task->tenant_id === current_tenant_id();

        return to_route($visibleHere ? 'tasks.show' : 'tasks.index', $visibleHere ? $task : [])->with('success', __('app.task_created'));
    }

    #[Authorize('view', 'task')]
    public function show(Task $task, Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('Tasks/Show', [
            'task' => $this->tasks->detail($task, $actor),
            'attachments' => $this->attachments->list($task, $actor, 'tasks.attachments.show'),
            'context' => $this->tasks->formContext($actor),
        ]);
    }

    #[Authorize('update', 'task')]
    public function update(TaskUpsertData $data, Task $task, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->tasks->update($task, $data, $actor);

        return to_route('tasks.show', $task)->with('success', __('app.task_updated'));
    }

    #[Authorize('delete', 'task')]
    public function destroy(Task $task): RedirectResponse
    {
        $this->tasks->delete($task);

        return to_route('tasks.index')->with('success', __('app.task_deleted'));
    }

    #[Authorize('update', 'task')]
    public function complete(Task $task): RedirectResponse
    {
        $this->tasks->complete($task);

        return back()->with('success', __('app.task_completed'));
    }

    #[Authorize('update', 'task')]
    public function reopen(Task $task): RedirectResponse
    {
        $this->tasks->reopen($task);

        return back()->with('success', __('app.task_reopened'));
    }

    #[Authorize('assign', 'task')]
    public function assign(TaskAssignData $data, Task $task, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->tasks->assign($task, $data, $actor);

        return back()->with('success', __('app.task_assigned'));
    }
}
