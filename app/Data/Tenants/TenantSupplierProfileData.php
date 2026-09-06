<?php

declare(strict_types=1);

namespace App\Data\Tenants;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Size;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TenantSupplierProfileData extends Data
{
    public function __construct(
        #[Nullable, Max(255)]
        public readonly ?string $address_line = null,
        #[Nullable, Max(255)]
        public readonly ?string $city = null,
        #[Nullable, Max(16)]
        public readonly ?string $postal_code = null,
        #[Size(2)]
        public readonly string $country = 'SK',
        #[Nullable, Max(20)]
        public readonly ?string $dic = null,
        #[Nullable, Max(20)]
        public readonly ?string $vat_number = null,
        public readonly bool $is_vat_payer = false,
        #[Nullable, Email, Max(255)]
        public readonly ?string $contact_email = null,
        #[Nullable, Max(30)]
        public readonly ?string $contact_phone = null,
        #[Nullable, Max(34), Regex('/^[A-Z]{2}\d{2}[A-Z0-9]{1,30}$/')]
        public readonly ?string $iban = null,
        #[Nullable, Max(11), Regex('/^[A-Z0-9]{8}([A-Z0-9]{3})?$/')]
        public readonly ?string $swift_bic = null,
    ) {}

    /**
     * @return array<string, mixed> tenant column => value
     */
    public function toTenantAttributes(): array
    {
        return [
            'address_line' => $this->address_line,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'dic' => $this->dic,
            'vat_number' => $this->vat_number,
            'is_vat_payer' => $this->is_vat_payer,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'iban' => $this->iban,
            'swift_bic' => $this->swift_bic,
        ];
    }
}
