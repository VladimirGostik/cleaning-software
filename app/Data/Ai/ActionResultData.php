<?php

declare(strict_types=1);

namespace App\Data\Ai;

use App\Enums\PendingActionEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ActionResultData extends Data
{
    public function __construct(
        public readonly PendingActionEnum $action,
        public readonly string $subject_label,
        public readonly ?string $url,
        public readonly string $message,
    ) {}
}
