<?php

declare(strict_types=1);

namespace App\Data\Objects;

use App\Enums\ObjectTypeEnum;
use App\Models\CleaningObject;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ObjectListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ObjectTypeEnum $type,
        public readonly string $name,
        public readonly ?string $city,
        public readonly bool $is_active,
        public readonly string $client_id,
        public readonly ?string $client_name,
        public readonly ?string $area_sqm,
        public readonly string $created_at,
    ) {}

    public static function fromModel(CleaningObject $object): self
    {
        return new self(
            id: $object->id,
            type: $object->type,
            name: $object->name,
            city: $object->city,
            is_active: (bool) $object->is_active,
            client_id: $object->client_id,
            client_name: $object->client?->name,
            area_sqm: $object->area_sqm,
            created_at: $object->created_at->toIso8601String(),
        );
    }
}
