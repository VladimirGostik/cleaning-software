<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PaymentSourceEnum: string
{
    case Manual = 'manual';
    case BankImport = 'bank_import';
}
