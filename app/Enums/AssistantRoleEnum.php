<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum AssistantRoleEnum: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
