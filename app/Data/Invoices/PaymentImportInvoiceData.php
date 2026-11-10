<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PaymentImportInvoiceData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $number,
        public readonly ?string $customer_name,
        public readonly InvoiceStatusEnum $status,
        public readonly string $issue_date,
        public readonly string $due_date,
        public readonly string $total,
        public readonly string $balance_due,
    ) {}

    public static function fromInvoice(Invoice $invoice): self
    {
        return new self(
            id: $invoice->id,
            number: (string) $invoice->number,
            customer_name: $invoice->customer_name,
            status: $invoice->status,
            issue_date: $invoice->issue_date->toDateString(),
            due_date: $invoice->due_date->toDateString(),
            total: number_format((float) $invoice->total, 2, '.', ''),
            balance_due: number_format($invoice->balance_due, 2, '.', ''),
        );
    }
}
