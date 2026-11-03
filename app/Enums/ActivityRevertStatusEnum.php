<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ActivityRevertStatusEnum: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Reverted = 'reverted';
}
