<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class QuoteInvoiceLinkData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $number,
        public readonly InvoiceStatusEnum $status,
    ) {}

    public static function fromModel(Invoice $invoice): self
    {
        return new self(
            id: $invoice->id,
            number: $invoice->number,
            status: $invoice->status,
        );
    }
}
