<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class NotificationBellData extends Data
{
    /** @param array<int, NotificationListItemData> $recent */
    public function __construct(
        public readonly int $unread_count,
        public readonly array $recent,
    ) {}

    /** Example-only shape for Scribe (`#[ResponseFromSpatieData]`) — real payload built by `NotificationService::bell()`. */
    public static function fromModel(User $user): self
    {
        return new self(unread_count: 0, recent: []);
    }
}
