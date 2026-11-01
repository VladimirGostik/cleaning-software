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

    #[Endpoint('Send assistant message', 'The client sends the last 20 messages (the last one from the user). 403 when the token has no mobile access or no use assistant permission in company_id; 422 with errors.ai when the AI provider is unavailable. A prepared irreversible action comes back as pending_action and is confirmed through /pending-actions.')]
    #[ResponseFromSpatieData(AssistantReplyData::class, User::class)]
    public function store(AssistantRequestData $data, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::forUser($actor)->authorize('chatIn', [Tenant::class, $data->company_id]);

        return response()->json($this->assistant->reply($data, $actor));
    }
}
