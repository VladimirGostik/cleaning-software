<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PendingActionStatusEnum: string
{
    case Pending = 'pending';
    case Executed = 'executed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
