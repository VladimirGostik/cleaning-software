<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ExpenseDuplicateActionEnum: string
{
    case KeepBoth = 'keep_both';
    case Replace = 'replace';
}
