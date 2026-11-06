<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum TaskIndexViewEnum: string
{
    case Table = 'table';
    case Board = 'board';
}
