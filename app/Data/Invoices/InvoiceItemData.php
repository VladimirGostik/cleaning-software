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
        public ?string $id,
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
        public ?float $line_base = null,
        public ?float $line_vat = null,
        public ?float $line_total = null,
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
