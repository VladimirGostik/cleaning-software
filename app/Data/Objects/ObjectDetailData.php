<?php

declare(strict_types=1);

namespace App\Data\Objects;

use App\Enums\ObjectTypeEnum;
use App\Models\CleaningObject;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ObjectDetailData extends Data
{
    public function __construct(
        public string $id,
        public string $client_id,
        public ?string $client_name,
        public ObjectTypeEnum $type,
        public string $name,
        public ?string $street,
        public ?string $city,
        public ?string $postal_code,
        public string $country,
        public ?string $access_code,
        public ?string $key_box_code,
        public ?int $key_count,
        public ?string $special_instructions,
        public ?string $area_sqm,
        public ?int $floor,
        public bool $is_active,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(CleaningObject $object): self
    {
        return new self(
            id: $object->id,
            client_id: $object->client_id,
            client_name: $object->client?->name,
            type: $object->type,
            name: $object->name,
            street: $object->street,
            city: $object->city,
            postal_code: $object->postal_code,
            country: $object->country,
            access_code: $object->access_code,
            key_box_code: $object->key_box_code,
            key_count: $object->key_count,
            special_instructions: $object->special_instructions,
            area_sqm: $object->area_sqm,
            floor: $object->floor,
            is_active: (bool) $object->is_active,
            created_at: $object->created_at->toIso8601String(),
            updated_at: $object->updated_at->toIso8601String(),
        );
    }
}
