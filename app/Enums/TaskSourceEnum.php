<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum TaskSourceEnum: string
{
    case Manual = 'manual';
    case Voice = 'voice';
    case Assistant = 'assistant';
    case Mcp = 'mcp';
}
