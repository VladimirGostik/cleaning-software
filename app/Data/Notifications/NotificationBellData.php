<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class NotificationBellData extends Data
{
    /**
     * @param  array<int, NotificationListItemData>  $recent
     */
    public function __construct(
        public int $unreadCount,
        public array $recent,
    ) {}
}
