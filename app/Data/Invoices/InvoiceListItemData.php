<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Models\Invoice;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceListItemData extends Data
{
    public function __construct(
        public string $id,
        public ?string $number,
        public InvoiceStatusEnum $status,
        public InvoiceTypeEnum $type,
        public string $customer_name,
        public string $total,
        public string $issue_date,
        public string $due_date,
        public ?string $client_id,
    ) {}

    public static function fromModel(Invoice $invoice): self
    {
        return new self(
            id: $invoice->id,
            number: $invoice->number,
            status: $invoice->status,
            type: $invoice->type,
            customer_name: $invoice->customer_name,
            total: $invoice->total,
            issue_date: $invoice->issue_date->toDateString(),
            due_date: $invoice->due_date->toDateString(),
            client_id: $invoice->client_id,
        );
    }
}
