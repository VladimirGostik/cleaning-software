<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Ai\AssistantConversationData;
use App\Data\Ai\AssistantConversationItemData;
use App\Data\Ai\AssistantConversationRenameData;
use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\Ai\AssistantConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;

#[Group('Assistant', 'In-app assistant over the same tool layer as MCP, without any way to confirm irreversible actions')]
#[Authenticated]
final class AssistantConversationController extends Controller
{
    public function __construct(private readonly AssistantConversationService $conversations) {}

    #[Authorize('viewAny', AssistantConversation::class)]
    #[Endpoint('List assistant conversations', 'The caller\'s own conversations, newest activity first, 20 per page (page, per_page). Shared by web and mobile; 403 without the use assistant permission in a company the token reaches.')]
    #[ResponseFromSpatieData(AssistantConversationItemData::class, User::class)]
    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->conversations->paginate($actor, $request));
    }

    #[Authorize('view', 'conversation')]
    #[Endpoint('Show assistant conversation', 'All stored messages oldest first. Prepared actions carry their live status. 404 for another user\'s conversation.')]
    #[ResponseFromSpatieData(AssistantConversationData::class, User::class)]
    public function show(AssistantConversation $conversation): JsonResponse
    {
        return response()->json($this->conversations->show($conversation));
    }

    #[Authorize('update', 'conversation')]
    #[Endpoint('Rename assistant conversation', 'Title 1 to 120 characters.')]
    #[ResponseFromSpatieData(AssistantConversationItemData::class, User::class)]
    public function update(AssistantConversation $conversation, AssistantConversationRenameData $data): JsonResponse
    {
        return response()->json($this->conversations->rename($conversation, $data));
    }

    #[Authorize('delete', 'conversation')]
    #[Endpoint('Delete assistant conversation', 'Permanently removes the conversation and its messages; prepared actions stay on Pending actions.')]
    public function destroy(AssistantConversation $conversation): Response
    {
        $this->conversations->delete($conversation);

        return response()->noContent();
    }
}
