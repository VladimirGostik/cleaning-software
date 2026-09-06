<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class NotificationPreferencesUpdateData extends Data
{
    /** @param array<int, NotificationPreferenceUpdateItemData> $preferences */
    public function __construct(
        #[DataCollectionOf(NotificationPreferenceUpdateItemData::class)]
        public readonly array $preferences,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'preferences' => ['present', 'array'],
        ];
    }
}
