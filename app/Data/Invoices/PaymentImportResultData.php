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

    public function withoutFreeText(): self
    {
        return new self(
            $this->recorded_count,
            $this->recorded_total,
            $this->already_imported,
            $this->excluded,
            $this->settled_invoices,
            $this->partly_paid_invoices,
            array_map(
                static fn (PaymentImportOverpaymentData $overpayment): PaymentImportOverpaymentData => new PaymentImportOverpaymentData(
                    $overpayment->reference,
                    $overpayment->invoice_id,
                    $overpayment->invoice_number,
                    null,
                    $overpayment->excess,
                ),
                $this->overpayments,
            ),
            $this->skipped,
        );
    }
}
