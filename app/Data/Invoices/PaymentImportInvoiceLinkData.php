<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PaymentImportInvoiceLinkData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $number,
        public readonly string $balance_due,
    ) {}
}
