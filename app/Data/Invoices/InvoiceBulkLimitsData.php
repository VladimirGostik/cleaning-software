<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceBulkLimitsData extends Data
{
    public function __construct(
        public readonly int $max_ids,
        public readonly int $pdf_max_ids,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            max_ids: config()->integer('invoicing.bulk.max_ids'),
            pdf_max_ids: config()->integer('invoicing.bulk.pdf_max_ids'),
        );
    }
}
