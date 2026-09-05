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
        public string $id,
        public ObjectTypeEnum $type,
        public string $name,
        public ?string $city,
        public bool $is_active,
        public string $client_id,
        public ?string $client_name,
        public ?string $area_sqm,
        public string $created_at,
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
