<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\PaymentImportRowStateEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PaymentImportSkippedRowData extends Data
{
    public function __construct(
        public readonly string $reference,
        public readonly PaymentImportRowStateEnum $state,
        public readonly ?string $invoice_number,
    ) {}
}
