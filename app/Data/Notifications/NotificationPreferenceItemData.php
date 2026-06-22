<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class NotificationPreferenceItemData extends Data
{
    public function __construct(
        public string $type,
        public string $label,
        public bool $mail,
        public bool $configurable,
    ) {}
}
