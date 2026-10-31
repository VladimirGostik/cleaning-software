<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Notifications\NotificationBellData;
use App\Data\Notifications\NotificationListItemData;
use App\Data\Notifications\NotificationPreferencesUpdateData;
use App\Enums\NotificationTypeEnum;
use App\Models\Notification;
use App\Models\User;
use App\Utils\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class NotificationService
{
    /**
     * @return LengthAwarePaginator<int, NotificationListItemData>
     */
    public function paginate(User $user, string $tenantId, Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Notification::query()->forRecipient($user)->inTenant($tenantId))
            ->allowedFilters(
                AllowedFilter::callbackClean('type', function (Builder $query, mixed $value): void {
                    if (! is_string($value) && ! is_int($value)) {
                        return;
                    }

                    $type = NotificationTypeEnum::tryFrom((string) $value);

                    if ($type instanceof NotificationTypeEnum) {
                        $query->where('type', $type->value);
                    }
                }),
                AllowedFilter::callbackClean('read', function (Builder $query, mixed $value): void {
                    $query->when(
                        filter_var($value, FILTER_VALIDATE_BOOL),
                        fn (Builder $q) => $q->whereNotNull('read_at'),
                        fn (Builder $q) => $q->whereNull('read_at'),
                    );
                })->boolean(),
            )
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (Notification $notification) => NotificationListItemData::fromModel($notification));
    }

    public function bell(User $user, string $tenantId): NotificationBellData
    {
        $recent = Notification::query()
            ->forRecipient($user)
            ->inTenant($tenantId)
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Notification $notification) => NotificationListItemData::fromModel($notification))
            ->all();

        return new NotificationBellData(
            unread_count: $this->unreadCount($user, $tenantId),
            recent: $recent,
        );
    }

    public function unreadCount(User $user, string $tenantId): int
    {
        return Notification::query()
            ->forRecipient($user)
            ->inTenant($tenantId)
            ->whereNull('read_at')
            ->count();
    }

    /** App-wide unread count over global rows and firms the user still belongs to; +1 when the notification's own row is not committed yet. */
    public function badgeFor(User $user, string $notificationId): int
    {
        $activeTenantIds = $user->tenants()
            ->wherePivot('is_active', true)
            ->where('tenants.is_active', true)
            ->select('tenants.id');

        $unread = Notification::query()
            ->forRecipient($user)
            ->whereNull('read_at')
            ->where(fn (Builder $query) => $query->whereNull('tenant_id')->orWhereIn('tenant_id', $activeTenantIds))
            ->count();

        return $unread + (Notification::query()->whereKey($notificationId)->exists() ? 0 : 1);
    }

    public function markRead(Notification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAllRead(User $user, string $tenantId): int
    {
        return Notification::query()
            ->forRecipient($user)
            ->inTenant($tenantId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function updatePreferences(User $user, NotificationPreferencesUpdateData $data): void
    {
        $preferences = $user->notification_preferences;

        foreach ($data->preferences as $item) {
            $preferences[$item->type->value] = ['mail' => $item->mail, 'push' => $item->push];
        }

        $user->update(['notification_preferences' => $preferences]);
    }
}
