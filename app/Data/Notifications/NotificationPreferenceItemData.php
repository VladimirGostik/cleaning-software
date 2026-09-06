<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Enums\NotificationTypeEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class NotificationPreferenceItemData extends Data
{
    public function __construct(
        public readonly NotificationTypeEnum $type,
        public readonly string $label,
        public readonly bool $mail,
        public readonly bool $configurable,
    ) {}
}
