<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum CustomerPartySourceEnum: string
{
    case ObjectBilling = 'object_billing';
    case Client = 'client';
    case Entered = 'entered';
}
