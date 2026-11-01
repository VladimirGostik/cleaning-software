<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DocumentTemplateStateEnum: string
{
    case Base = 'base';
    case Customized = 'customized';
    case BaseChanged = 'base_changed';
}
