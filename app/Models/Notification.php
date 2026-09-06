<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationTypeEnum;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $type NotificationTypeEnum value
 * @property string $notifiable_type
 * @property string $notifiable_id
 * @property string $tenant_id
 * @property array<string, mixed> $data {type,title,body,url,meta}
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 */
final class Notification extends DatabaseNotification
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeInTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeForRecipient(Builder $query, User $user): Builder
    {
        return $query
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->notifiable_type === $user->getMorphClass()
            && $this->notifiable_id === $user->id;
    }

    public function typeEnum(): NotificationTypeEnum
    {
        return NotificationTypeEnum::from($this->type);
    }
}
