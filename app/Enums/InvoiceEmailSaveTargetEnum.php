<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum InvoiceEmailSaveTargetEnum: string
{
    case RecurringInvoice = 'recurring_invoice';
    case Object = 'object';
}
