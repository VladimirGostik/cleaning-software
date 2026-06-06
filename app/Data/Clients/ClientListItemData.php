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
        public string $id,
        public ClientTypeEnum $type,
        public string $name,
        public ?string $ico,
        public ?string $city,
        public int $contacts_count,
        public int $objects_count,         // TODO: replace with real count when objects module lands
        public int $active_contracts_count, // TODO: replace with real count when contracts module lands
        public ?string $primary_contact_email,
        public ?string $primary_contact_phone,
        public string $created_at,
    ) {}

    public static function fromModel(Client $client): self
    {
        /** @var ClientTypeEnum $type */
        $type = $client->type;

        $primaryContact = $client->contacts->firstWhere('is_primary', true);

        return new self(
            id: $client->id,
            type: $type,
            name: $client->name,
            ico: $client->ico,
            city: $client->city,
            contacts_count: (int) ($client->contacts_count ?? 0),
            objects_count: 0,
            active_contracts_count: 0,
            primary_contact_email: $primaryContact?->email,
            primary_contact_phone: $primaryContact?->phone,
            created_at: $client->created_at->toIso8601String(),
        );
    }
}
