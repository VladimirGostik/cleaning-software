<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Enums\NotificationTypeEnum;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class NotificationPreferencesData extends Data
{
    /** @param array<int, NotificationPreferenceItemData> $items */
    public function __construct(
        public readonly array $items,
    ) {}

    public static function fromUser(User $user): self
    {
        $stored = $user->notification_preferences;

        $items = array_map(
            fn (NotificationTypeEnum $type): NotificationPreferenceItemData => new NotificationPreferenceItemData(
                type: $type,
                label: $type->label(),
                mail: $type->userConfigurable()
                    ? (bool) ($stored[$type->value]['mail'] ?? $type->defaultMailEnabled())
                    : $type->alwaysMail(),
                configurable: $type->userConfigurable(),
            ),
            NotificationTypeEnum::cases(),
        );

        return new self(items: $items);
    }
}
