<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Models\InvoiceItem;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceItemData extends Data
{
    public function __construct(
        public readonly ?string $id,
        #[Required]
        public readonly string $description,
        #[Required, Min(0)]
        public readonly float $quantity,
        #[Nullable]
        public readonly ?string $unit,
        #[Required, Min(0)]
        public readonly float $unit_price,
        #[Min(0), Between(0, 100)]
        public readonly float $discount_percent = 0,
        #[Min(0)]
        public readonly float $vat_rate = 0,
        public readonly ?float $line_base = null,
        public readonly ?float $line_vat = null,
        public readonly ?float $line_total = null,
    ) {}

    public static function fromModel(InvoiceItem $item): self
    {
        return new self(
            id: $item->id,
            description: $item->description,
            quantity: (float) $item->quantity,
            unit: $item->unit,
            unit_price: (float) $item->unit_price,
            discount_percent: (float) $item->discount_percent,
            vat_rate: (float) $item->vat_rate,
            line_base: (float) $item->line_base,
            line_vat: (float) $item->line_vat,
            line_total: (float) $item->line_total,
        );
    }
}
