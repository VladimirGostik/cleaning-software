<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PaymentImportResultData extends Data
{
    /**
     * @param  list<PaymentImportInvoiceLinkData>  $settled_invoices
     * @param  list<PaymentImportInvoiceLinkData>  $partly_paid_invoices
     * @param  list<PaymentImportOverpaymentData>  $overpayments
     * @param  list<PaymentImportSkippedRowData>  $skipped
     */
    public function __construct(
        public readonly int $recorded_count,
        public readonly string $recorded_total,
        public readonly int $already_imported,
        public readonly int $excluded,
        public readonly array $settled_invoices,
        public readonly array $partly_paid_invoices,
        public readonly array $overpayments,
        public readonly array $skipped,
    ) {}
}
