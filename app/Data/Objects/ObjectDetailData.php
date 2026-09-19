<?php

declare(strict_types=1);

namespace App\Data\Objects;

use App\Enums\ObjectTypeEnum;
use App\Models\CleaningObject;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ObjectDetailData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $client_id,
        public readonly ?string $client_name,
        public readonly ObjectTypeEnum $type,
        public readonly string $name,
        public readonly ?string $street,
        public readonly ?string $city,
        public readonly ?string $postal_code,
        public readonly string $country,
        public readonly ?string $access_code,
        public readonly ?string $key_box_code,
        public readonly ?int $key_count,
        public readonly ?string $special_instructions,
        public readonly ?string $area_sqm,
        public readonly ?int $floor,
        public readonly bool $is_active,
        public readonly string $created_at,
        /**
         * Q1 gate: `null` = actor may not see object contacts at all; `[]` = permitted actor,
         * this object simply has none. Never conflate the two — see `ObjectPolicy::viewContacts()`.
         *
         * @var DataCollection<int, ObjectContactData>|null
         */
        #[DataCollectionOf(ObjectContactData::class)]
        public readonly ?DataCollection $contacts,
    ) {}

    public static function fromModel(CleaningObject $object, bool $includeContacts): self
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
            contacts: $includeContacts ? ObjectContactData::collect($object->contacts, DataCollection::class) : null,
        );
    }
}
