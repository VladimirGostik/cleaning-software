<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use Illuminate\Notifications\DatabaseNotification;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class NotificationListItemData extends Data
{
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public string $body,
        public ?string $url,
        public ?string $readAt,
        public string $createdAt,
    ) {}

    public static function fromModel(DatabaseNotification $notification): self
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return new self(
            id: $notification->id,
            type: (string) ($data['type'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            body: (string) ($data['body'] ?? ''),
            url: isset($data['url']) ? (string) $data['url'] : null,
            readAt: $notification->read_at?->toIso8601String(),
            createdAt: $notification->created_at->toIso8601String(),
        );
    }
}
