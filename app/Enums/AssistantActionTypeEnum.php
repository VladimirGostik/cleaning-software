<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum AssistantActionTypeEnum: string
{
    case Open = 'open';
    case Created = 'created';
    case Updated = 'updated';
}
