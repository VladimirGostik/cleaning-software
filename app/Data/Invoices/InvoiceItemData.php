<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Models\InvoiceItem;
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
        public ?float $total,
    ) {}

    public static function fromModel(InvoiceItem $item): self
    {
        return new self(
            id: $item->id,
            description: $item->description,
            quantity: (float) $item->quantity,
            unit: $item->unit,
            unit_price: (float) $item->unit_price,
            total: (float) $item->total,
        );
    }
}
