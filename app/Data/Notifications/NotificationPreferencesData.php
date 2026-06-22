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
    /**
     * @param  array<int, NotificationPreferenceItemData>  $items
     */
    public function __construct(
        public array $items,
    ) {}

    public static function fromUser(User $user): self
    {
        /** @var array<string, array<string, bool>>|null $stored */
        $stored = $user->notification_preferences;

        $items = array_map(function (NotificationTypeEnum $type) use ($stored): NotificationPreferenceItemData {
            $mailEnabled = $stored[$type->value]['mail'] ?? $type->defaultMailEnabled();

            return new NotificationPreferenceItemData(
                type: $type->value,
                label: $type->label(),
                mail: $mailEnabled,
                configurable: $type->userConfigurable(),
            );
        }, NotificationTypeEnum::cases());

        return new self(items: array_values($items));
    }
}
