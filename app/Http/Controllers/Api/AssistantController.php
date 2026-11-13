<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Ai\AssistantReplyData;
use App\Data\Ai\AssistantRequestData;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\Ai\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;

#[Group('Assistant', 'In-app assistant over the same tool layer as MCP, without any way to confirm irreversible actions')]
#[Authenticated]
final class AssistantController extends Controller
{
    public function __construct(private readonly AssistantService $assistant) {}

    #[Endpoint('Send assistant message', 'The client sends conversation_id (null starts a new conversation), a client_message_id (uuid per user message, reused on retry: a stored turn is returned without calling the AI again) and content; the server keeps the history. 403 when the token has no mobile access or no use assistant permission in company_id; 422 with errors.ai when the AI provider is unavailable. A prepared irreversible action comes back as pending_action and is confirmed through /pending-actions. Send multipart/form-data to attach up to 5 files (attachments[], JPEG/PNG/WebP up to 5 MB, PDF up to 10 MB and 10 pages, 12 MB and 20 PDF pages in total) to the message; the files are saved onto the expenses and tasks created in that message and attachment_note says what happened. Only the file names are kept in the history. 422 on conversation_id (deleted or not yours), client_message_id (used by another user), content (empty without files, too long, conversation full), attachments; 413 above the PHP post size.')]
    #[ResponseFromSpatieData(AssistantReplyData::class, User::class)]
    public function store(AssistantRequestData $data, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::forUser($actor)->authorize('chatIn', [Tenant::class, $data->company_id]);

        return response()->json($this->assistant->reply($data, $actor));
    }
}
