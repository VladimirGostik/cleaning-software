<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceBulkIdsData extends Data
{
    /** @param list<string> $ids */
    public function __construct(
        public readonly array $ids,
        public readonly int $total,
        public readonly bool $truncated,
    ) {}
}
