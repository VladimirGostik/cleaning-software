<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PaymentImportRowInputData extends Data
{
    public function __construct(
        #[Required, Max(100)]
        public readonly string $reference,
        #[BooleanType]
        public readonly bool $include,
        #[Nullable, Uuid]
        public readonly ?string $invoice_id = null,
    ) {}
}
