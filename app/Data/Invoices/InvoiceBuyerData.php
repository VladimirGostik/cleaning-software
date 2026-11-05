<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\CustomerPartySourceEnum;
use App\Support\CustomerParty;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceBuyerData extends Data
{
    public function __construct(
        public readonly CustomerPartySourceEnum $source,
        public readonly ?string $name,
        public readonly ?string $represented_by,
        public readonly ?string $representative,
        public readonly ?string $ico,
        public readonly ?string $dic,
        public readonly ?string $vat_number,
        public readonly ?string $street,
        public readonly ?string $city,
        public readonly ?string $postal_code,
        public readonly ?string $country,
        public readonly ?string $email,
    ) {}

    public static function fromParty(CustomerParty $party): self
    {
        return new self(
            source: $party->source,
            name: $party->name,
            represented_by: $party->representedBy,
            representative: $party->representative,
            ico: $party->ico,
            dic: $party->dic,
            vat_number: $party->vatNumber,
            street: $party->street,
            city: $party->city,
            postal_code: $party->postalCode,
            country: $party->country,
            email: $party->email,
        );
    }
}
