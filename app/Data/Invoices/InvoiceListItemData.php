<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Models\Invoice;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $number,
        public readonly InvoiceStatusEnum $status,
        public readonly InvoiceTypeEnum $type,
        public readonly string $customer_name,
        public readonly ?string $client_id,
        public readonly ?string $client_name,
        public readonly ?string $object_name,
        public readonly CurrencyEnum $currency,
        public readonly string $total,
        public readonly string $balance_due,
        public readonly string $issue_date,
        public readonly string $due_date,
        public readonly bool $is_credit_note,
    ) {}

    public static function fromModel(Invoice $invoice): self
    {
        return new self(
            id: $invoice->id,
            number: $invoice->number,
            status: $invoice->status,
            type: $invoice->type,
            customer_name: $invoice->customer_name,
            client_id: $invoice->client_id,
            client_name: $invoice->client?->name,
            object_name: $invoice->object_name,
            currency: $invoice->currency,
            total: $invoice->total,
            balance_due: (string) $invoice->balance_due,
            issue_date: $invoice->issue_date->toDateString(),
            due_date: $invoice->due_date->toDateString(),
            is_credit_note: $invoice->credited_invoice_id !== null,
        );
    }
}
