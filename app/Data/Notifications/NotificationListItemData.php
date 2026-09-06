<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Enums\NotificationTypeEnum;
use App\Models\Notification;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class NotificationListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly NotificationTypeEnum $type,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url,
        public readonly ?string $read_at,
        public readonly string $created_at,
    ) {}

    public static function fromModel(Notification $notification): self
    {
        /** @var array{title?: string, body?: string, url?: string|null} $data */
        $data = $notification->data;

        return new self(
            id: $notification->id,
            type: $notification->typeEnum(),
            title: $data['title'] ?? '',
            body: $data['body'] ?? '',
            url: $data['url'] ?? null,
            read_at: $notification->read_at?->toIso8601String(),
            created_at: $notification->created_at->toIso8601String(),
        );
    }
}
