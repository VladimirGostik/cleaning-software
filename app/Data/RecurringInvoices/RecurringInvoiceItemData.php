<?php

declare(strict_types=1);

namespace App\Data\RecurringInvoices;

use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RecurringInvoiceItemData extends Data
{
    public function __construct(
        #[Required]
        public string $description,
        #[Required, Min(0)]
        public float $quantity,
        #[Nullable]
        public ?string $unit,
        #[Required, Min(0)]
        public float $unit_price,
        #[Min(0), Between(0, 100)]
        public float $discount_percent = 0,
        #[Min(0)]
        public float $vat_rate = 0,
    ) {}
}
