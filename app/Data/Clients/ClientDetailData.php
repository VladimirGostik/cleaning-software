<?php

declare(strict_types=1);

namespace App\Data\Clients;

use App\Enums\ClientTypeEnum;
use App\Models\Client;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ClientDetailData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ClientTypeEnum $type,
        public readonly string $name,
        public readonly ?string $ico,
        public readonly ?string $dic,
        public readonly ?string $vat_number,
        public readonly bool $is_vat_payer,
        public readonly ?string $street,
        public readonly ?string $city,
        public readonly ?string $postal_code,
        public readonly string $country,
        public readonly ?string $note,
        /** @var DataCollection<int, ClientContactData> */
        #[DataCollectionOf(ClientContactData::class)]
        public readonly DataCollection $contacts,
        public readonly string $created_at,
    ) {}

    public static function fromModel(Client $client): self
    {
        return new self(
            id: $client->id,
            type: $client->type,
            name: $client->name,
            ico: $client->ico,
            dic: $client->dic,
            vat_number: $client->vat_number,
            is_vat_payer: $client->is_vat_payer,
            street: $client->street,
            city: $client->city,
            postal_code: $client->postal_code,
            country: $client->country,
            note: $client->note,
            contacts: ClientContactData::collect($client->contacts, DataCollection::class),
            created_at: $client->created_at->toIso8601String(),
        );
    }
}
