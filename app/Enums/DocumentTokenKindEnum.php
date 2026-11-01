<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DocumentTokenKindEnum: string
{
    case Text = 'text';
    case Optional = 'optional';
    case Block = 'block';
    case Flag = 'flag';
}
