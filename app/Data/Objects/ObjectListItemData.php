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
        /** Q1 gate: `null` = actor may not see contacts. See `ObjectPolicy::viewContacts()`. */
        public readonly ?int $contacts_count,
        public readonly ?string $primary_contact_email,
        public readonly ?string $primary_contact_phone,
    ) {}

    public static function fromModel(CleaningObject $object, bool $includeContacts): self
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
            contacts_count: $includeContacts ? (int) ($object->contacts_count ?? 0) : null,
            primary_contact_email: $includeContacts ? $object->primaryContact?->email : null,
            primary_contact_phone: $includeContacts ? $object->primaryContact?->phone : null,
        );
    }
}
