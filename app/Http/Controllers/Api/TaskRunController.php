<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Tasks\TaskRunStartData;
use App\Data\Tasks\TaskRunStatusData;
use App\Data\Tasks\TaskThreadData;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\TaskRunService;
use App\Services\TaskThreadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;

#[Group('Tasks', 'Firm and global tasks')]
#[Authenticated]
final class TaskRunController extends Controller
{
    public function __construct(private readonly TaskRunService $runs, private readonly TaskThreadService $threads) {}

    #[Authorize('view', 'task')]
    #[Endpoint('AI run status', 'Slim poll for the latest run: id, status and whether it is still active. All empty when there is no run or the caller is outside its audience. A run that can no longer be alive is ended on this read.')]
    #[ResponseFromSpatieData(TaskRunStatusData::class, User::class)]
    public function status(Task $task, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->threads->runStatus($task, $actor));
    }

    #[Authorize('view', 'task')]
    #[Endpoint('Start an AI run', 'Queues a background run with the caller\'s rights. company_id is required for a personal task. 422 on status, ai or company_id; 403 without run tasks with ai.')]
    #[ResponseFromSpatieData(TaskThreadData::class, User::class, status: 202)]
    public function store(TaskRunStartData $data, Task $task, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->runs->start($task, $data, $actor);

        return response()->json($this->threads->for($task, $actor), 202);
    }
}
