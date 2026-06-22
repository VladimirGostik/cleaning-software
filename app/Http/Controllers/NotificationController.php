<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Notifications\NotificationIndexFilterData;
use App\Data\Notifications\NotificationListItemData;
use App\Data\Notifications\NotificationPreferencesData;
use App\Data\Notifications\NotificationPreferencesUpdateData;
use App\Enums\NotificationTypeEnum;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\LaravelData\PaginatedDataCollection;

final class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $service) {}

    #[Authorize('viewAny', DatabaseNotification::class)]
    public function index(NotificationIndexFilterData $filter, Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = (string) app('current_tenant_id');

        $paginator = $this->service->paginate($filter, $user, $tenantId);

        return Inertia::render('Notifications/Index', [
            'notifications' => NotificationListItemData::collect(
                $paginator->through(fn (DatabaseNotification $n) => NotificationListItemData::fromModel($n)),
                PaginatedDataCollection::class,
            ),
            'filters' => $filter,
            'typeOptions' => NotificationTypeEnum::inAppOptions(),
        ]);
    }

    #[Authorize('update', 'notification')]
    public function markRead(DatabaseNotification $notification): RedirectResponse
    {
        $this->service->markRead($notification);

        return back()->with('flash.success', __('app.notifications.marked_read'));
    }

    #[Authorize('viewAny', DatabaseNotification::class)]
    public function markAllRead(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = (string) app('current_tenant_id');

        $this->service->markAllRead($user, $tenantId);

        return back()->with('flash.success', __('app.notifications.all_marked_read'));
    }

    public function settings(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Settings/Notifications', [
            'preferences' => NotificationPreferencesData::fromUser($user),
        ]);
    }

    public function updateSettings(NotificationPreferencesUpdateData $data, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->service->updatePreferences($user, $data);

        return back()->with('flash.success', __('app.notification_settings.saved'));
    }
}
