<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceStatCardData extends Data
{
    public function __construct(
        public string $amount,
        public int $count,
    ) {}
}
