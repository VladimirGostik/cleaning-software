<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ActionChannelEnum: string
{
    case Mcp = 'mcp';
    case Assistant = 'assistant';
    case TaskRun = 'task_run';
}
