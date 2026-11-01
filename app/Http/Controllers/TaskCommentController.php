<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Tasks\TaskCommentStoreData;
use App\Data\Tasks\TaskThreadData;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskCommentService;
use App\Services\TaskThreadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

final class TaskCommentController extends Controller
{
    public function __construct(private readonly TaskCommentService $comments, private readonly TaskThreadService $threads) {}

    #[Authorize('view', 'task')]
    public function thread(Task $task, Request $request): TaskThreadData
    {
        /** @var User $actor */
        $actor = $request->user();

        return $this->threads->for($task, $actor);
    }

    #[Authorize('comment', 'task')]
    public function store(TaskCommentStoreData $data, Task $task, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->comments->add($task, $data, $actor);

        return back();
    }

    #[Authorize('view', 'task')]
    public function destroy(Task $task, string $comment, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->comments->delete($task, $comment, $actor);

        return back()->with('success', __('app.task_comment_deleted'));
    }
}
