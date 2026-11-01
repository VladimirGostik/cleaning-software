<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Ai\ActionResultData;
use App\Data\Ai\PendingActionData;
use App\Enums\ConfirmationChannelEnum;
use App\Http\Controllers\Controller;
use App\Models\PendingAction;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\Ai\ActionConfirmationService;
use App\Services\Dashboard\TenantVisibility;
use App\Services\Tenancy\TenantPermissionSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;

#[Group('Pending actions', 'Two-step confirmation of irreversible actions prepared by AI tools')]
#[Authenticated]
final class PendingActionController extends Controller
{
    public function __construct(private readonly ActionConfirmationService $actions) {}

    #[Authorize('viewAny', PendingAction::class)]
    #[Endpoint('List pending actions', 'Open, unexpired actions of the caller in companies where the app is allowed. Never contains a token.')]
    #[ResponseFromSpatieData(PendingActionData::class, User::class)]
    public function index(Request $request, TenantPermissionSnapshot $snapshot): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $tenantIds = TenantVisibility::idsWhere($snapshot->forRequest($actor), fn (TenantVisibility $visibility): bool => true);

        return response()->json(PendingActionData::collect($this->actions->pendingFor($actor, $tenantIds)));
    }

    #[Authorize('confirm', 'pendingAction')]
    #[Endpoint('Confirm pending action', 'Executes the action under its own company, whatever company is active. Refused where the user lacks mobile access.')]
    #[ResponseFromSpatieData(ActionResultData::class, User::class)]
    public function confirm(PendingAction $pendingAction, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->actions->execute($actor, $pendingAction, ConfirmationChannelEnum::App));
    }

    #[Authorize('cancel', 'pendingAction')]
    #[Endpoint('Cancel pending action', 'Discards an open action.')]
    #[ResponseFromSpatieData(PendingActionData::class, User::class)]
    public function cancel(PendingAction $pendingAction, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json(PendingActionData::fromModel($this->actions->cancel($actor, $pendingAction)));
    }
}
