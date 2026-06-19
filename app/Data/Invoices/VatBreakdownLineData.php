<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class VatBreakdownLineData extends Data
{
    public function __construct(
        public float $rate,
        public float $base,
        public float $vat,
        public float $total,
    ) {}
}
