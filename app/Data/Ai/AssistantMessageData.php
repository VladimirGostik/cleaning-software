<?php

declare(strict_types=1);

namespace App\Data\Ai;

use App\Enums\AssistantRoleEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AssistantMessageData extends Data
{
    public function __construct(
        public readonly AssistantRoleEnum $role,
        public readonly string $content,
    ) {}
}
