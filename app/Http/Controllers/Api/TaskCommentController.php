<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Tasks\TaskCommentStoreData;
use App\Data\Tasks\TaskThreadData;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\TaskCommentService;
use App\Services\TaskThreadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;

#[Group('Tasks', 'Firm and global tasks')]
#[Authenticated]
final class TaskCommentController extends Controller
{
    public function __construct(private readonly TaskCommentService $comments, private readonly TaskThreadService $threads) {}

    #[Authorize('view', 'task')]
    #[Endpoint('Task thread', 'Comments, the latest AI run, its open question and the viewer\'s pending actions. AI output is filtered per viewer.')]
    #[ResponseFromSpatieData(TaskThreadData::class, User::class)]
    public function thread(Task $task, Request $request): JsonResponse
    {
        return response()->json($this->threads->for($task, $this->actor($request)));
    }

    #[Authorize('comment', 'task')]
    #[Endpoint('Comment on a task', 'With answers_comment_id the comment answers the open AI question and starts nothing. 422 on body, answers_comment_id or status.')]
    #[ResponseFromSpatieData(TaskThreadData::class, User::class, status: 201)]
    public function store(TaskCommentStoreData $data, Task $task, Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $this->comments->add($task, $data, $actor);

        return response()->json($this->threads->for($task, $actor), 201);
    }

    #[Authorize('view', 'task')]
    #[Endpoint('Delete a comment', 'The author or a holder of delete tasks. A comment the viewer cannot see answers 404.')]
    #[ResponseFromSpatieData(TaskThreadData::class, User::class)]
    public function destroy(Task $task, string $comment, Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $this->comments->delete($task, $comment, $actor);

        return response()->json($this->threads->for($task, $actor));
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
