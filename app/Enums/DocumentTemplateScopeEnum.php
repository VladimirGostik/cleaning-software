<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DocumentTemplateScopeEnum: string
{
    case Global = 'global';
    case Override = 'override';
    case Firm = 'firm';
}
