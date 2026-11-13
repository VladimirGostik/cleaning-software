<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Models\ScheduledJobItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class JobWorkRecordItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $unit_price,
        public readonly ?string $object_visit_item_id,
    ) {}

    public static function fromModel(ScheduledJobItem $item, bool $withPrice): self
    {
        return new self(
            id: $item->id,
            name: $item->name,
            unit_price: $withPrice ? number_format((float) $item->unit_price, 2, '.', '') : null,
            object_visit_item_id: $item->object_visit_item_id,
        );
    }
}
