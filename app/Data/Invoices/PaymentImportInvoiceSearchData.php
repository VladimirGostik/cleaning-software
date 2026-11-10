<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PaymentImportInvoiceSearchData extends Data
{
    public function __construct(
        #[Nullable, Max(100)]
        public readonly ?string $search = null,
        #[Nullable, Rule(['numeric', 'gt:0', 'decimal:0,2'])]
        public readonly ?string $amount = null,
    ) {}
}
