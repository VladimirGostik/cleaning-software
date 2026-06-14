<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class BulkInvoiceData extends Data
{
    public function __construct(
        #[Required, In('mark_paid')]
        public string $action,
        /** @var array<int, string> */
        #[Required, ArrayType, Min(1), Max(200)]
        public array $ids,
    ) {}
}
