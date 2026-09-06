<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class VatBreakdownLineData extends Data
{
    public function __construct(
        public readonly float $rate,
        public readonly float $base,
        public readonly float $vat,
        public readonly float $total,
    ) {}
}
