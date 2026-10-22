<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum BulkActionEnum: string
{
    case Pay = 'pay';
    case Send = 'send';
    case Issue = 'issue';
}
