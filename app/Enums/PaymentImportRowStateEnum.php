<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PaymentImportRowStateEnum: string
{
    case AlreadyImported = 'already_imported';
    case NoVs = 'no_vs';
    case VsNotFound = 'vs_not_found';
    case InvoiceNotPayable = 'invoice_not_payable';
    case InvoicePaid = 'invoice_paid';
    case PreviouslyDeleted = 'previously_deleted';
    case PossibleDuplicate = 'possible_duplicate';
    case Overpaid = 'overpaid';
    case Partial = 'partial';
    case Matched = 'matched';

    public function label(): string
    {
        return __('app.payment_import_row_state_'.$this->value);
    }
}
