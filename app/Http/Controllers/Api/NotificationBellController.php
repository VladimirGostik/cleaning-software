<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Notifications\NotificationBellData;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;

/** Mobile-phase twin of the web `/notifications/bell` route (D4 — `/api` is not stateful). */
#[Group('Notifications', 'In-app notifications')]
#[Authenticated]
final class NotificationBellController extends Controller
{
    #[Endpoint('Bell', 'Unread count + 5 latest notifications for the active tenant.')]
    #[ResponseFromSpatieData(NotificationBellData::class, User::class)]
    #[Authorize('viewAny', Notification::class)]
    public function __invoke(Request $request, NotificationService $notifications): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($notifications->bell($user, current_tenant_id()));
    }
}
