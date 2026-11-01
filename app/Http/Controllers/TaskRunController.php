<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Tasks\TaskRunStartData;
use App\Data\Tasks\TaskRunStatusData;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskRunService;
use App\Services\TaskThreadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

final class TaskRunController extends Controller
{
    public function __construct(private readonly TaskRunService $runs, private readonly TaskThreadService $threads) {}

    #[Authorize('view', 'task')]
    public function status(Task $task, Request $request): TaskRunStatusData
    {
        /** @var User $actor */
        $actor = $request->user();

        return $this->threads->runStatus($task, $actor);
    }

    #[Authorize('view', 'task')]
    public function store(TaskRunStartData $data, Task $task, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->runs->start($task, $data, $actor);

        return back()->with('success', __('app.task_run_started'));
    }
}
