<?php

declare(strict_types=1);

namespace App\Data\Objects;

use App\Models\CleaningObject;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ObjectBillingData extends Data
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $ico,
        public readonly ?string $dic,
        public readonly ?string $vat_number,
        public readonly ?string $peppol_participant_id,
        public readonly ?string $street,
        public readonly ?string $city,
        public readonly ?string $postal_code,
        public readonly ?string $email,
    ) {}

    public static function fromModel(CleaningObject $object): ?self
    {
        if (! $object->hasBillingIdentity() && blank($object->billing_email)) {
            return null;
        }

        return new self(
            name: $object->billing_name,
            ico: $object->billing_ico,
            dic: $object->billing_dic,
            vat_number: $object->billing_vat_number,
            peppol_participant_id: $object->billing_peppol_participant_id,
            street: $object->billing_street,
            city: $object->billing_city,
            postal_code: $object->billing_postal_code,
            email: $object->billing_email,
        );
    }
}
