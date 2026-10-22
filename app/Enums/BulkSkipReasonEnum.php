<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum BulkSkipReasonEnum: string
{
    case NotFound = 'not_found';
    case NotIssued = 'not_issued';
    case NoEmail = 'no_email';
    case NotDraft = 'not_draft';
    case NotPayable = 'not_payable';
    case SupplierIncomplete = 'supplier_incomplete';
    case NumberTaken = 'number_taken';
}
