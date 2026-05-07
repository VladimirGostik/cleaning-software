<?php

declare(strict_types=1);

namespace App\Data\Clients;

use App\Enums\ClientType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ClientDetailData extends Data
{
    public function __construct(
        public string $id,
        public ClientType $type,
        public string $name,
        public ?string $ico,
        public ?string $dic,
        public ?string $vat_number,
        public bool $is_vat_payer,
        public ?string $street,
        public ?string $city,
        public ?string $postal_code,
        public string $country,
        public ?string $note,
        /** @var DataCollection<int, ClientContactData> */
        #[DataCollectionOf(ClientContactData::class)]
        public DataCollection $contacts,
        /** @var array<int, mixed> */
        public array $objects = [],   // empty placeholder until objects module
        /** @var array<int, mixed> */
        public array $contracts = [], // empty placeholder until contracts module
        /** @var array<int, mixed> */
        public array $invoices = [],  // empty placeholder until invoices module
        public string $created_at = '',
        public string $updated_at = '',
    ) {}
}
