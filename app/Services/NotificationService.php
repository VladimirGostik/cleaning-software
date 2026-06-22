<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Notifications\NotificationBellData;
use App\Data\Notifications\NotificationIndexFilterData;
use App\Data\Notifications\NotificationListItemData;
use App\Data\Notifications\NotificationPreferencesUpdateData;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class NotificationService
{
    public function paginate(
        NotificationIndexFilterData $filter,
        User $user,
        string $tenantId,
    ): LengthAwarePaginator {
        return $user->notifications()
            ->where('tenant_id', $tenantId)
            ->when($filter->unreadOnly, fn ($q) => $q->whereNull('read_at'))
            ->when($filter->type, fn ($q) => $q->where('type', $filter->type))
            ->latest()
            ->paginate($filter->perPage);
    }

    public function bell(User $user, string $tenantId): NotificationBellData
    {
        $unreadCount = $this->unreadCount($user, $tenantId);

        $recent = $user->notifications()
            ->where('tenant_id', $tenantId)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (DatabaseNotification $n) => NotificationListItemData::fromModel($n))
            ->all();

        return new NotificationBellData(
            unreadCount: $unreadCount,
            recent: $recent,
        );
    }

    public function unreadCount(User $user, string $tenantId): int
    {
        return $user->unreadNotifications()
            ->where('tenant_id', $tenantId)
            ->count();
    }

    public function markRead(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAllRead(User $user, string $tenantId): void
    {
        $user->unreadNotifications()
            ->where('tenant_id', $tenantId)
            ->update(['read_at' => now()]);
    }

    public function updatePreferences(User $user, NotificationPreferencesUpdateData $data): void
    {
        $existing = $user->notification_preferences ?? [];

        foreach ($data->preferences as $type => $mailEnabled) {
            $existing[$type] = array_merge($existing[$type] ?? [], ['mail' => $mailEnabled]);
        }

        $user->update(['notification_preferences' => $existing]);
    }
}
