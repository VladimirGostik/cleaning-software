<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PaymentImportOverpaymentData extends Data
{
    public function __construct(
        public readonly string $reference,
        public readonly string $invoice_id,
        public readonly string $invoice_number,
        public readonly ?string $payer_name,
        public readonly string $excess,
    ) {}
}
