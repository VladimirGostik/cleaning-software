<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Tasks\TaskAttachmentData;
use App\Data\Tasks\TaskAttachmentListData;
use App\Data\Tasks\TaskAttachmentUploadData;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\TaskAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group('Tasks', 'Firm and global tasks')]
#[Authenticated]
final class TaskAttachmentController extends Controller
{
    public function __construct(private readonly TaskAttachmentService $attachments) {}

    #[Authorize('view', 'task')]
    #[Endpoint('List task attachments', 'Oldest first, at most the per-task limit. Includes upload constraints and whether the caller may add files.')]
    #[ResponseFromSpatieData(TaskAttachmentListData::class, User::class)]
    public function index(Task $task, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->attachments->list($task, $actor, 'api.tasks.attachments.show'));
    }

    #[Authorize('update', 'task')]
    #[Endpoint('Add task attachment', 'Multipart upload, one file per request. Photos, PDF, Word and Excel, up to 10 MB, 20 per task.')]
    #[BodyParam('file', 'file', 'The file to attach.')]
    #[ResponseFromSpatieData(TaskAttachmentData::class, User::class, status: 201)]
    public function store(TaskAttachmentUploadData $data, Task $task, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $media = $this->attachments->add($task, $data->file, $actor);

        return response()->json($this->attachments->present($task, $media, $actor, 'api.tasks.attachments.show'), 201);
    }

    #[Authorize('view', 'task')]
    #[Endpoint('Download task attachment', 'Streams the file. Images and PDF are inline, everything else is an attachment.')]
    public function show(Task $task, string $attachment): StreamedResponse
    {
        return $this->attachments->stream($this->attachments->find($task, $attachment));
    }

    #[Authorize('view', 'task')]
    #[Endpoint('Delete task attachment', 'The uploader, a holder of delete tasks, or the creator of a global task.')]
    public function destroy(Task $task, string $attachment, Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->attachments->delete($task, $this->attachments->find($task, $attachment), $actor);

        return response()->noContent();
    }
}
