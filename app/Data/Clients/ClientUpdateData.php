<?php

declare(strict_types=1);

namespace App\Data\Clients;

use App\Enums\ClientType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\RequiredIf;
use Spatie\LaravelData\Attributes\Validation\Size;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ClientUpdateData extends Data
{
    public function __construct(
        #[Required]
        public ClientType $type,
        #[Required, Max(255)]
        public string $name,
        #[Nullable, Max(32), RequiredIf('type', 'corporate')]
        public ?string $ico,
        #[Nullable, Max(32)]
        public ?string $dic,
        #[Nullable, Max(32)]
        public ?string $vat_number,
        public bool $is_vat_payer,
        #[Nullable, Max(255)]
        public ?string $street,
        #[Nullable, Max(255)]
        public ?string $city,
        #[Nullable, Max(16)]
        public ?string $postal_code,
        #[Required, Size(2)]
        public string $country,
        #[Nullable]
        public ?string $note,
        /** @var DataCollection<int, ClientContactData> */
        #[DataCollectionOf(ClientContactData::class)]
        public DataCollection $contacts,
    ) {}
}
