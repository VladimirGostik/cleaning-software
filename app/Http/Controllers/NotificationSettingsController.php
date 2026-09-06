<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Notifications\NotificationPreferencesData;
use App\Data\Notifications\NotificationPreferencesUpdateData;
use App\Enums\PermissionEnum;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class NotificationSettingsController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    #[Authorize(PermissionEnum::ConfigureNotifications->value)]
    #[NavItem(label: 'app.notification_settings', route: 'settings.notifications', icon: 'BellAlertIcon', permission: PermissionEnum::ConfigureNotifications->value, group: 'settings', order: 25)]
    public function show(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Settings/Notifications', [
            'preferences' => NotificationPreferencesData::fromUser($user),
        ]);
    }

    #[Authorize(PermissionEnum::ConfigureNotifications->value)]
    public function update(NotificationPreferencesUpdateData $data, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->notifications->updatePreferences($user, $data);

        return to_route('settings.notifications')->with('success', __('app.notification_settings_saved'));
    }
}
