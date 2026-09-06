<?php

declare(strict_types=1);

namespace App\Data\Clients;

use App\Enums\ClientTypeEnum;
use App\Models\Client;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ClientListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ClientTypeEnum $type,
        public readonly string $name,
        public readonly ?string $ico,
        public readonly ?string $city,
        public readonly int $contacts_count,
        public readonly int $objects_count,
        public readonly ?string $primary_contact_email,
        public readonly ?string $primary_contact_phone,
        public readonly string $created_at,
    ) {}

    public static function fromModel(Client $client): self
    {
        return new self(
            id: $client->id,
            type: $client->type,
            name: $client->name,
            ico: $client->ico,
            city: $client->city,
            contacts_count: (int) ($client->contacts_count ?? 0),
            objects_count: (int) ($client->objects_count ?? 0),
            primary_contact_email: $client->primaryContact?->email,
            primary_contact_phone: $client->primaryContact?->phone,
            created_at: $client->created_at->toIso8601String(),
        );
    }
}
