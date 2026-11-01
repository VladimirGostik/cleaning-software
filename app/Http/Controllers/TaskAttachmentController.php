<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Tasks\TaskAttachmentUploadData;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskAttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TaskAttachmentController extends Controller
{
    public function __construct(private readonly TaskAttachmentService $attachments) {}

    #[Authorize('update', 'task')]
    public function store(TaskAttachmentUploadData $data, Task $task, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->attachments->add($task, $data->file, $actor);

        return back();
    }

    #[Authorize('view', 'task')]
    public function show(Task $task, string $attachment): StreamedResponse
    {
        return $this->attachments->stream($this->attachments->find($task, $attachment));
    }

    #[Authorize('view', 'task')]
    public function destroy(Task $task, string $attachment, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->attachments->delete($task, $this->attachments->find($task, $attachment), $actor);

        return back()->with('success', __('app.task_attachment_deleted'));
    }
}
