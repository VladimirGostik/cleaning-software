<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\Notification;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    #[Authorize('viewAny', Notification::class)]
    #[NavItem(label: 'app.notifications', route: 'notifications.index', icon: 'BellIcon', permission: PermissionEnum::ViewNotifications->value, order: 45)]
    public function index(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = current_tenant_id();

        return Inertia::render('Notifications/Index', [
            'notifications' => $this->notifications->paginate($user, $tenantId, $request),
            'filters' => $request->query(),
            'typeOptions' => NotificationTypeEnum::inAppOptions(),
            'unreadCount' => $this->notifications->unreadCount($user, $tenantId),
        ]);
    }

    #[Authorize('viewAny', Notification::class)]
    public function bell(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->notifications->bell($user, current_tenant_id()));
    }

    #[Authorize('update', 'notification')]
    public function markRead(Notification $notification): RedirectResponse
    {
        $this->notifications->markRead($notification);

        return back()->with('success', __('app.notification_marked_read'));
    }

    #[Authorize('viewAny', Notification::class)]
    public function markAllRead(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->notifications->markAllRead($user, current_tenant_id());

        return back()->with('success', __('app.notifications_all_marked_read'));
    }
}
