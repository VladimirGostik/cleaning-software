<?php

declare(strict_types=1);

namespace App\Data\Ai;

use App\Enums\AssistantActionTypeEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AssistantActionData extends Data
{
    public function __construct(
        public readonly AssistantActionTypeEnum $type,
        public readonly string $label,
        public readonly ?string $url,
    ) {}
}
