<?php

declare(strict_types=1);

namespace App\Data\Objects;

use App\Models\ObjectVisitItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ObjectVisitItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $price,
        public readonly int $position,
    ) {}

    public static function fromModel(ObjectVisitItem $item): self
    {
        return new self(
            id: $item->id,
            name: $item->name,
            price: number_format((float) $item->price, 2, '.', ''),
            position: $item->position,
        );
    }
}
