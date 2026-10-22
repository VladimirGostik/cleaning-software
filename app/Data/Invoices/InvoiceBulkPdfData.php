<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceBulkPdfData extends Data
{
    /** @param list<string> $ids */
    public function __construct(
        public readonly array $ids,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.config()->integer('invoicing.bulk.pdf_max_ids')],
            'ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
