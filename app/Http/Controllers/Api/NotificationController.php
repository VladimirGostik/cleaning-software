<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Notifications\NotificationListItemData;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\NotificationService;
use App\Utils\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;

#[Group('Notifications', 'In-app notifications')]
#[Authenticated]
final class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    #[Authorize('viewAny', Notification::class)]
    #[Endpoint('List notifications', 'Own notifications of the active company plus company-less ones.')]
    #[QueryParam('filter[type]', 'string', 'Notification type.', required: false)]
    #[QueryParam('filter[read]', 'boolean', 'true = read only, false = unread only.', required: false)]
    #[ResponseFromSpatieData(NotificationListItemData::class, User::class, paginated: true)]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->notifications->paginate($user, current_tenant_id(), PerPage::clamp($request)));
    }

    #[Authorize('update', 'notification')]
    #[Endpoint('Mark read', 'Marks one own notification as read.')]
    public function read(Notification $notification): Response
    {
        $this->notifications->markRead($notification);

        return response()->noContent();
    }

    #[Authorize('viewAny', Notification::class)]
    #[Endpoint('Mark all read', 'Marks all own notifications of the active company (and company-less ones) as read.')]
    public function readAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['updated' => $this->notifications->markAllRead($user, current_tenant_id())]);
    }
}
