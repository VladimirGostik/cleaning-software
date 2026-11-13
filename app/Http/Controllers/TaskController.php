<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Tasks\TaskAssignData;
use App\Data\Tasks\TaskPatchData;
use App\Data\Tasks\TaskStatusChangeData;
use App\Data\Tasks\TaskUpsertData;
use App\Enums\NavIconEnum;
use App\Enums\PermissionEnum;
use App\Enums\TaskIndexViewEnum;
use App\Models\Task;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\TaskActivityService;
use App\Services\TaskAttachmentService;
use App\Services\TaskService;
use App\Services\TaskThreadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

final class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskAttachmentService $attachments,
        private readonly TaskThreadService $threads,
        private readonly TaskActivityService $activity,
    ) {}

    #[Authorize('viewAny', Task::class)]
    #[NavItem(label: 'app.tasks', route: 'tasks.index', icon: NavIconEnum::Tasks, permission: PermissionEnum::ViewTasks->value, order: 12)]
    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $view = $this->resolveView($request);
        $selected = $request->filled('task') ? $this->tasks->findVisible($request->string('task')->toString(), $actor) : null;

        return Inertia::render('Tasks/Index', [
            'view' => $view,
            'tasks' => fn () => $view === TaskIndexViewEnum::Table ? $this->tasks->paginate($request, $actor) : null,
            'board' => fn () => $view === TaskIndexViewEnum::Board ? $this->tasks->board($request, $actor) : null,
            'filters' => $request->query(),
            'context' => fn () => $this->tasks->formContext($actor),
            'selected_task' => fn () => $selected === null ? null : $this->tasks->detail($selected, $actor),
            'selected_attachments' => fn () => $selected === null ? null : $this->attachments->list($selected, $actor, 'tasks.attachments.show'),
            'selected_thread' => fn () => $selected === null ? null : $this->threads->for($selected, $actor),
            'selected_activity' => Inertia::optional(fn () => $selected === null ? null : $this->activity->for($selected, $actor)),
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

        return to_route('tasks.index', $visibleHere ? ['task' => $task->id] : [])->with('success', __('app.task_created'));
    }

    #[Authorize('view', 'task')]
    public function show(Task $task): RedirectResponse
    {
        return to_route('tasks.index', ['task' => $task->id]);
    }

    #[Authorize('update', 'task')]
    public function update(TaskUpsertData $data, Task $task, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->tasks->update($task, $data, $actor);

        return back()->with('success', __('app.task_updated'));
    }

    #[Authorize('update', 'task')]
    public function patch(TaskPatchData $data, Task $task, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->tasks->patch($task, $data, $actor);

        return back();
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

    #[Authorize('update', 'task')]
    public function status(TaskStatusChangeData $data, Task $task): RedirectResponse
    {
        $this->tasks->changeStatus($task, $data->status);

        return back()->with('success', __('app.task_status_changed'));
    }

    #[Authorize('assign', 'task')]
    public function assign(TaskAssignData $data, Task $task, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->tasks->assign($task, $data, $actor);

        return back()->with('success', __('app.task_assigned'));
    }

    private function resolveView(Request $request): TaskIndexViewEnum
    {
        $requested = TaskIndexViewEnum::tryFrom($request->string('view')->toString());

        if ($requested !== null) {
            $request->session()->put('tasks.index_view', $requested->value);

            return $requested;
        }

        $stored = $request->session()->get('tasks.index_view');

        return (is_string($stored) ? TaskIndexViewEnum::tryFrom($stored) : null) ?? TaskIndexViewEnum::Table;
    }
}
