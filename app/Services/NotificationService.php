<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Notifications\NotificationBellData;
use App\Data\Notifications\NotificationListItemData;
use App\Data\Notifications\NotificationPreferencesUpdateData;
use App\Enums\NotificationTypeEnum;
use App\Enums\PermissionEnum;
use App\Jobs\SyncPushBadge;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\BaseTenantNotification;
use App\Services\Dashboard\TenantVisibility;
use App\Services\Tenancy\TenantPermissionSnapshot;
use App\Utils\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class NotificationService
{
    public function __construct(
        private TenantPermissionSnapshot $snapshot,
        private DeviceTokenService $devices,
    ) {}

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

    public function bell(User $user, string $tenantId, bool $withBadge = false): NotificationBellData
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
            badge_count: $withBadge ? $this->badgeCount($user) : null,
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

    /** Unread rows over the companies the phone shows (mobile access + view notifications) and company-less rows; +1 for a pending row not stored yet. */
    public function badgeCount(User $user, ?BaseTenantNotification $pending = null): int
    {
        $companyIds = $this->phoneCompanyIds($user);
        $count = $this->phoneUnread($user, $companyIds)->count();

        if ($pending === null || Notification::query()->whereKey($pending->id)->exists()) {
            return $count;
        }

        $inScope = $pending->tenantId === null ? $companyIds !== [] : in_array($pending->tenantId, $companyIds, true);

        return $count + ($inScope ? 1 : 0);
    }

    public function markRead(Notification $notification, User $user): void
    {
        $wasUnread = $notification->read_at === null;

        $notification->markAsRead();

        if ($wasUnread) {
            $this->syncBadge($user);
        }
    }

    public function markAllRead(User $user, string $tenantId): int
    {
        $updated = Notification::query()
            ->forRecipient($user)
            ->inTenant($tenantId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updated > 0) {
            $this->syncBadge($user);
        }

        return $updated;
    }

    public function markAllReadOnPhone(User $user): int
    {
        $updated = $this->phoneUnread($user, $this->phoneCompanyIds($user))->update(['read_at' => now()]);

        if ($updated > 0) {
            $this->syncBadge($user);
        }

        return $updated;
    }

    private function syncBadge(User $user): void
    {
        if ($this->devices->hasIosTokens($user)) {
            SyncPushBadge::dispatch($user->id)->afterCommit();
        }
    }

    /** @return list<string> */
    private function phoneCompanyIds(User $user): array
    {
        return TenantVisibility::idsWhere(
            $this->snapshot->for($user),
            static fn (TenantVisibility $visibility): bool => $visibility->mobileAccess
                && in_array(PermissionEnum::ViewNotifications, $visibility->permissions, true),
        );
    }

    /**
     * @param  list<string>  $companyIds
     * @return Builder<Notification>
     */
    private function phoneUnread(User $user, array $companyIds): Builder
    {
        $query = Notification::query()->forRecipient($user)->whereNull('read_at');

        if ($companyIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $inner) => $inner->whereNull('tenant_id')->orWhereIn('tenant_id', $companyIds));
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
