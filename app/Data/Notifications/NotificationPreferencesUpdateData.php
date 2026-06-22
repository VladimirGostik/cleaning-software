<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Enums\NotificationTypeEnum;
use Spatie\LaravelData\Data;

final class NotificationPreferencesUpdateData extends Data
{
    /**
     * @param  array<string, bool>  $preferences
     */
    public function __construct(
        public array $preferences,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $configurableValues = array_map(
            fn (NotificationTypeEnum $t) => $t->value,
            array_filter(NotificationTypeEnum::cases(), fn (NotificationTypeEnum $t) => $t->userConfigurable()),
        );

        return [
            'preferences' => [
                'required',
                'array',
                function (string $attribute, mixed $value, callable $fail) use ($configurableValues): void {
                    foreach (array_keys($value) as $key) {
                        if (! in_array($key, $configurableValues, true)) {
                            $fail("The key '{$key}' is not a configurable notification type.");
                        }
                    }
                },
            ],
            'preferences.*' => ['boolean'],
        ];
    }
}
