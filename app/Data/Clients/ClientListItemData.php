<?php

declare(strict_types=1);

namespace App\Data\Clients;

use App\Enums\ClientType;
use App\Models\Client;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ClientListItemData extends Data
{
    public function __construct(
        public string $id,
        public ClientType $type,
        public string $name,
        public ?string $ico,
        public ?string $email,
        public ?string $phone,
        public ?string $city,
        public int $contacts_count,
        public int $objects_count,         // TODO: replace with real count when objects module lands
        public int $active_contracts_count, // TODO: replace with real count when contracts module lands
        public string $created_at,
    ) {}

    public static function fromModel(Client $client): self
    {
        /** @var ClientType $type */
        $type = $client->type;

        return new self(
            id: $client->id,
            type: $type,
            name: $client->name,
            ico: $client->ico,
            email: $client->email,
            phone: $client->phone,
            city: $client->city,
            contacts_count: (int) ($client->contacts_count ?? 0),
            objects_count: 0,
            active_contracts_count: 0,
            created_at: $client->created_at->toIso8601String(),
        );
    }
}
