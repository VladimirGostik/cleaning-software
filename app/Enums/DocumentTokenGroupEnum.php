<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DocumentTokenGroupEnum: string
{
    case Tenant = 'tenant';
    case Contract = 'contract';
    case Client = 'client';
    case Object = 'object';
    case Employee = 'employee';
    case Employment = 'employment';
    case Customer = 'customer';
    case Quote = 'quote';
    case Conditions = 'conditions';
    case Blocks = 'blocks';
}
